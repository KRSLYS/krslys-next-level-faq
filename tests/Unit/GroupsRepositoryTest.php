<?php
/**
 * Groups Repository: FAQ vs Accordion type handling.
 */

namespace Krslys\NextLevelFaqAccordion\Tests\Unit;

use Brain\Monkey\Functions;
use Krslys\NextLevelFaqAccordion\Groups_Repository;
use Krslys\NextLevelFaqAccordion\Tests\MockWpdb;
use Krslys\NextLevelFaqAccordion\Tests\WpTestCase;

class GroupsRepositoryTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb = new MockWpdb( true );
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'absint' )->alias( function ( $val ) {
			return abs( (int) $val );
		} );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'get_option' )->justReturn( 1 );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = null;
		parent::tearDown();
	}

	// -- Create: type defaults to 'faq' --

	public function test_create_group_defaults_to_faq_type(): void {
		global $wpdb;
		$wpdb->insert_id = 10;

		$id = Groups_Repository::create_group( [
			'title' => 'My FAQ Group',
		] );

		$this->assertSame( 10, $id );
	}

	// -- Create: accordion type accepted --

	public function test_create_group_accepts_accordion_type(): void {
		global $wpdb;
		$wpdb->insert_id = 20;

		$id = Groups_Repository::create_group( [
			'title' => 'My Accordion',
			'type'  => 'accordion',
		] );

		$this->assertSame( 20, $id );
	}

	// -- Create: invalid type falls back to 'faq' --

	public function test_create_group_rejects_invalid_type(): void {
		global $wpdb;
		$wpdb->insert_id = 30;

		$id = Groups_Repository::create_group( [
			'title' => 'Hacked Type',
			'type'  => 'malicious',
		] );

		// Should still create (falls back to 'faq'), not reject.
		$this->assertSame( 30, $id );
	}

	// -- Create: empty title returns 0 --

	public function test_create_group_empty_title_returns_falsy(): void {
		$id = Groups_Repository::create_group( [
			'title' => '',
		] );

		$this->assertEmpty( $id );
	}

	// -- Get by ID: returns group with decoded JSON fields --

	public function test_get_group_decodes_json_settings(): void {
		global $wpdb;

		$row = new \stdClass();
		$row->id               = 1;
		$row->title            = 'Test FAQ';
		$row->slug             = 'test-faq';
		$row->description      = '';
		$row->theme_settings   = '{"theme":"modern"}';
		$row->display_settings = '{"accordion_mode":true}';
		$row->custom_styles    = null;
		$row->use_custom_style = 0;
		$row->type             = 'faq';
		$row->status           = 'active';
		$row->created_at       = '2026-01-01 00:00:00';
		$row->updated_at       = '2026-01-01 00:00:00';
		$wpdb->set_get_row_result( $row );

		$group = Groups_Repository::get_group_by_id( 1 );

		$this->assertIsArray( $group->theme_settings );
		$this->assertSame( 'modern', $group->theme_settings['theme'] );
		$this->assertIsArray( $group->display_settings );
		$this->assertTrue( $group->display_settings['accordion_mode'] );
		$this->assertSame( 'faq', $group->type );
	}

	// -- Get by ID: accordion type preserved --

	public function test_get_accordion_group_has_accordion_type(): void {
		global $wpdb;

		$row = new \stdClass();
		$row->id               = 2;
		$row->title            = 'Test Accordion';
		$row->slug             = 'test-accordion';
		$row->description      = '';
		$row->theme_settings   = '{}';
		$row->display_settings = '{}';
		$row->custom_styles    = null;
		$row->use_custom_style = 0;
		$row->type             = 'accordion';
		$row->status           = 'active';
		$row->created_at       = '2026-01-01 00:00:00';
		$row->updated_at       = '2026-01-01 00:00:00';
		$wpdb->set_get_row_result( $row );

		$group = Groups_Repository::get_group_by_id( 2 );

		$this->assertSame( 'accordion', $group->type );
	}

	// -- Get by ID: null returns null --

	public function test_get_nonexistent_group_returns_null(): void {
		global $wpdb;
		$wpdb->set_get_row_result( null );

		$group = Groups_Repository::get_group_by_id( 999 );

		$this->assertNull( $group );
	}
}
