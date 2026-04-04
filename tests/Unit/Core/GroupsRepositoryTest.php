<?php
/**
 * Tests for the Groups_Repository class.
 *
 * Every method that touches the database is exercised through MockWpdb so no
 * real database connection is required.
 *
 * @package Krslys\NextLevelFaq\Tests\Unit\Core
 */

namespace Krslys\NextLevelFaq\Tests\Unit\Core;

use Krslys\NextLevelFaq\Groups_Repository;
use Krslys\NextLevelFaq\Tests\MockWpdb;
use Krslys\NextLevelFaq\Tests\WpTestCase;

/**
 * @covers \Krslys\NextLevelFaq\Groups_Repository
 */
class GroupsRepositoryTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Default: tables do NOT exist → guard clauses return early.
		global $wpdb;
		$wpdb = new MockWpdb( false );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = null;
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Helper: build a complete mock group row with all columns that
	// decode_group_json() touches (including use_custom_style).
	// -----------------------------------------------------------------------

	private function make_row( array $overrides = [] ): \stdClass {
		$defaults = [
			'id'               => 1,
			'title'            => 'Test Group',
			'slug'             => 'test-group',
			'status'           => 'active',
			'description'      => '',
			'theme_settings'   => null,
			'display_settings' => null,
			'custom_styles'    => null,
			'use_custom_style' => 0,
			'created_at'       => '2024-01-01 00:00:00',
			'updated_at'       => '2024-01-01 00:00:00',
		];

		$row = new \stdClass();
		foreach ( array_merge( $defaults, $overrides ) as $k => $v ) {
			$row->$k = $v;
		}
		return $row;
	}

	// -----------------------------------------------------------------------
	// get_group_by_id — guard clauses
	// -----------------------------------------------------------------------

	public function test_get_group_by_id_returns_null_when_tables_missing(): void {
		$this->assertNull( Groups_Repository::get_group_by_id( 1 ) );
	}

	public function test_get_group_by_id_returns_null_for_zero_id_with_tables_missing(): void {
		$this->assertNull( Groups_Repository::get_group_by_id( 0 ) );
	}

	// -----------------------------------------------------------------------
	// get_group_by_id — row found
	// -----------------------------------------------------------------------

	public function test_get_group_by_id_returns_group_object_when_found(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );

		$wpdb->set_get_row_result( $this->make_row( [ 'id' => 5, 'title' => 'Test Group' ] ) );

		$result = Groups_Repository::get_group_by_id( 5 );

		$this->assertNotNull( $result );
		$this->assertSame( 5, (int) $result->id );
		$this->assertSame( 'Test Group', $result->title );
	}

	// -----------------------------------------------------------------------
	// get_group_by_id — row not found
	// -----------------------------------------------------------------------

	public function test_get_group_by_id_returns_null_when_row_not_found(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );
		// get_row_result defaults to null → group not found.

		$result = Groups_Repository::get_group_by_id( 999 );
		$this->assertNull( $result );
	}

	// -----------------------------------------------------------------------
	// get_group_by_slug — row not found
	// -----------------------------------------------------------------------

	public function test_get_group_by_slug_returns_null_when_not_found(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );
		// get_row_result defaults to null.

		$result = Groups_Repository::get_group_by_slug( 'no-such-slug' );
		$this->assertNull( $result );
	}

	// -----------------------------------------------------------------------
	// get_all_groups — empty result
	// -----------------------------------------------------------------------

	public function test_get_all_groups_returns_empty_array_when_no_rows(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );
		// get_results_result defaults to [] → empty.

		$result = Groups_Repository::get_all_groups();
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_get_all_groups_with_status_filter_returns_empty_array(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );

		$result = Groups_Repository::get_all_groups( 'active' );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	// -----------------------------------------------------------------------
	// get_all_groups — rows present
	// -----------------------------------------------------------------------

	public function test_get_all_groups_returns_decoded_groups(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );

		$wpdb->set_get_results_result( [
			$this->make_row( [ 'id' => 1, 'title' => 'Group A', 'slug' => 'group-a' ] ),
			$this->make_row( [ 'id' => 2, 'title' => 'Group B', 'slug' => 'group-b' ] ),
		] );

		$result = Groups_Repository::get_all_groups();

		$this->assertCount( 2, $result );
		$this->assertSame( 'Group A', $result[0]->title );
		$this->assertSame( 'Group B', $result[1]->title );
	}

	// -----------------------------------------------------------------------
	// create_group — validation
	// -----------------------------------------------------------------------

	public function test_create_group_returns_false_when_title_is_empty(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );

		$this->assertFalse( Groups_Repository::create_group( [ 'title' => '' ] ) );
	}

	public function test_create_group_returns_false_when_title_is_missing(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );

		$this->assertFalse( Groups_Repository::create_group( [] ) );
	}

	// -----------------------------------------------------------------------
	// JSON field decoding
	// -----------------------------------------------------------------------

	public function test_json_theme_settings_are_decoded_to_array(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );

		$wpdb->set_get_row_result( $this->make_row( [
			'id'             => 3,
			'title'          => 'JSON Group',
			'slug'           => 'json-group',
			'theme_settings' => json_encode( [ 'layout' => 'cards' ] ),
		] ) );

		$result = Groups_Repository::get_group_by_id( 3 );

		$this->assertNotNull( $result );
		$this->assertIsArray( $result->theme_settings );
		$this->assertSame( 'cards', $result->theme_settings['layout'] );
	}

	public function test_null_json_fields_become_empty_arrays(): void {
		// decode_group_json() normalises null/empty JSON columns to [] for
		// consistent consumer access — asserting that contract here.
		global $wpdb;
		$wpdb = new MockWpdb( true );

		$wpdb->set_get_row_result( $this->make_row( [ 'id' => 4, 'theme_settings' => null ] ) );

		$result = Groups_Repository::get_group_by_id( 4 );

		$this->assertIsArray( $result->theme_settings );
		$this->assertEmpty( $result->theme_settings );
	}

	public function test_use_custom_style_is_cast_to_bool(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );

		$wpdb->set_get_row_result( $this->make_row( [ 'use_custom_style' => 1 ] ) );

		$result = Groups_Repository::get_group_by_id( 1 );

		$this->assertTrue( $result->use_custom_style );
	}
}
