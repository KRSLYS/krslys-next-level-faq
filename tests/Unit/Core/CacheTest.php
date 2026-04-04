<?php
/**
 * Tests for the Cache class.
 *
 * @package Krslys\NextLevelFaq\Tests\Unit\Core
 */

namespace Krslys\NextLevelFaq\Tests\Unit\Core;

use Brain\Monkey\Functions;
use Krslys\NextLevelFaq\Cache;
use Krslys\NextLevelFaq\Tests\WpTestCase;

/**
 * @covers \Krslys\NextLevelFaq\Cache
 */
class CacheTest extends WpTestCase {

	// -----------------------------------------------------------------------
	// Constants
	// -----------------------------------------------------------------------

	public function test_group_constant_value(): void {
		$this->assertSame( 'nlf_faq_cache', Cache::GROUP );
	}

	// -----------------------------------------------------------------------
	// get_rendered_group — guard clauses
	// -----------------------------------------------------------------------

	public function test_get_rendered_group_returns_false_for_zero_id(): void {
		// No WP functions should be called for an invalid ID.
		$this->assertFalse( Cache::get_rendered_group( 0, [] ) );
	}

	public function test_get_rendered_group_returns_false_for_negative_id(): void {
		$this->assertFalse( Cache::get_rendered_group( -1, [] ) );
	}

	public function test_get_rendered_group_returns_false_for_string_zero(): void {
		// PHP's <= comparison: '0' <= 0 is true after type juggling.
		$this->assertFalse( Cache::get_rendered_group( 0, [ 'key' => 'value' ] ) );
	}

	// -----------------------------------------------------------------------
	// get_rendered_group — cache miss / hit
	// -----------------------------------------------------------------------

	public function test_get_rendered_group_returns_false_on_full_cache_miss(): void {
		Functions\when( 'get_option' )->justReturn( 1 );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( false );

		$this->assertFalse( Cache::get_rendered_group( 1, [] ) );
	}

	public function test_get_rendered_group_returns_html_from_object_cache(): void {
		$html = '<div class="nlf-faq">cached</div>';

		Functions\when( 'get_option' )->justReturn( 1 );
		Functions\when( 'wp_cache_get' )->justReturn( $html );

		$result = Cache::get_rendered_group( 1, [] );
		$this->assertSame( $html, $result );
	}

	public function test_get_rendered_group_returns_html_from_transient_on_object_cache_miss(): void {
		$html = '<div class="nlf-faq">transient</div>';

		Functions\when( 'get_option' )->justReturn( 2 );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( $html );
		Functions\when( 'wp_cache_set' )->justReturn( true );

		$result = Cache::get_rendered_group( 5, [] );
		$this->assertSame( $html, $result );
	}

	// -----------------------------------------------------------------------
	// set_rendered_group — guard clauses
	// -----------------------------------------------------------------------

	public function test_set_rendered_group_returns_false_for_zero_id(): void {
		$this->assertFalse( Cache::set_rendered_group( 0, [], '<p>HTML</p>' ) );
	}

	public function test_set_rendered_group_returns_false_for_negative_id(): void {
		$this->assertFalse( Cache::set_rendered_group( -5, [], '<p>HTML</p>' ) );
	}

	// -----------------------------------------------------------------------
	// set_rendered_group — happy path
	// -----------------------------------------------------------------------

	public function test_set_rendered_group_returns_true_for_valid_id(): void {
		Functions\when( 'get_option' )->justReturn( 1 );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'set_transient' )->justReturn( true );

		$result = Cache::set_rendered_group( 1, [], '<p>test</p>' );
		$this->assertTrue( $result );
	}

	public function test_set_rendered_group_calls_both_cache_and_transient(): void {
		$cache_calls    = 0;
		$transient_calls = 0;

		Functions\when( 'get_option' )->justReturn( 1 );
		Functions\when( 'wp_cache_set' )->alias( function () use ( &$cache_calls ) {
			$cache_calls++;
			return true;
		} );
		Functions\when( 'set_transient' )->alias( function () use ( &$transient_calls ) {
			$transient_calls++;
			return true;
		} );

		Cache::set_rendered_group( 3, [ 'preset' => 'modern' ], '<p>html</p>' );

		$this->assertSame( 1, $cache_calls, 'wp_cache_set should be called once' );
		$this->assertSame( 1, $transient_calls, 'set_transient should be called once' );
	}

	// -----------------------------------------------------------------------
	// invalidate_group — guard clauses
	// -----------------------------------------------------------------------

	public function test_invalidate_group_does_nothing_for_zero_id(): void {
		$called = false;
		Functions\when( 'update_option' )->alias( function () use ( &$called ) {
			$called = true;
		} );

		Cache::invalidate_group( 0 );

		$this->assertFalse( $called, 'update_option must not be called for group_id 0' );
	}

	public function test_invalidate_group_does_nothing_for_negative_id(): void {
		$called = false;
		Functions\when( 'update_option' )->alias( function () use ( &$called ) {
			$called = true;
		} );

		Cache::invalidate_group( -3 );

		$this->assertFalse( $called, 'update_option must not be called for group_id -3' );
	}

	// -----------------------------------------------------------------------
	// invalidate_group — version bumping
	// -----------------------------------------------------------------------

	public function test_invalidate_group_bumps_version_by_one(): void {
		$updated_key   = null;
		$updated_value = null;

		Functions\when( 'get_option' )->justReturn( 4 );
		Functions\when( 'update_option' )->alias(
			function ( $key, $value ) use ( &$updated_key, &$updated_value ) {
				$updated_key   = $key;
				$updated_value = $value;
			}
		);

		Cache::invalidate_group( 7 );

		$this->assertSame( 'nlf_faq_cache_version_7', $updated_key );
		$this->assertSame( 5, $updated_value );
	}

	public function test_invalidate_group_treats_missing_version_as_one(): void {
		$updated_key   = null;
		$updated_value = null;

		// get_option returns false (not set) → version defaults to 1 → bumped to 2.
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'update_option' )->alias(
			function ( $key, $value ) use ( &$updated_key, &$updated_value ) {
				$updated_key   = $key;
				$updated_value = $value;
			}
		);

		Cache::invalidate_group( 2 );

		$this->assertSame( 'nlf_faq_cache_version_2', $updated_key );
		$this->assertSame( 2, $updated_value );
	}

	// -----------------------------------------------------------------------
	// Key stability — same context, same group → same cache key
	// -----------------------------------------------------------------------

	public function test_same_group_and_context_produce_same_key(): void {
		$context = [ 'preset' => 'minimal', 'layout' => 'flat' ];
		$html    = '<p>cached</p>';

		Functions\when( 'get_option' )->justReturn( 1 );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( $html );
		Functions\when( 'wp_cache_set' )->justReturn( true );

		$result1 = Cache::get_rendered_group( 10, $context );

		Functions\when( 'wp_cache_get' )->justReturn( $html );

		$result2 = Cache::get_rendered_group( 10, $context );

		$this->assertSame( $result1, $result2 );
	}
}
