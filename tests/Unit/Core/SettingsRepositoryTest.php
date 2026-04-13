<?php
/**
 * Tests for the Settings_Repository class.
 *
 * Because Settings_Repository uses $wpdb directly we swap in MockWpdb so no
 * real database connection is required.
 *
 * @package Krslys\NextLevelFaqAccordion\Tests\Unit\Core
 */

namespace Krslys\NextLevelFaqAccordion\Tests\Unit\Core;

use Krslys\NextLevelFaqAccordion\Settings_Repository;
use Krslys\NextLevelFaqAccordion\Tests\MockWpdb;
use Krslys\NextLevelFaqAccordion\Tests\WpTestCase;

/**
 * @covers \Krslys\NextLevelFaqAccordion\Settings_Repository
 */
class SettingsRepositoryTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Default: tables do NOT exist → early-return paths are exercised.
		global $wpdb;
		$wpdb = new MockWpdb( false );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = null;
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Constants
	// -----------------------------------------------------------------------

	public function test_key_global_styles_constant(): void {
		$this->assertSame( 'global_styles', Settings_Repository::KEY_GLOBAL_STYLES );
	}

	public function test_key_active_preset_constant(): void {
		$this->assertSame( 'active_preset', Settings_Repository::KEY_ACTIVE_PRESET );
	}

	public function test_key_cache_config_constant(): void {
		$this->assertSame( 'cache_config', Settings_Repository::KEY_CACHE_CONFIG );
	}

	// -----------------------------------------------------------------------
	// get_setting — tables missing
	// -----------------------------------------------------------------------

	public function test_get_setting_returns_null_default_when_tables_missing(): void {
		$result = Settings_Repository::get_setting( 'any_key' );
		$this->assertNull( $result );
	}

	public function test_get_setting_returns_custom_default_when_tables_missing(): void {
		$result = Settings_Repository::get_setting( 'any_key', 'my_default' );
		$this->assertSame( 'my_default', $result );
	}

	public function test_get_setting_returns_array_default_when_tables_missing(): void {
		$default = [ 'foo' => 'bar' ];
		$result  = Settings_Repository::get_setting( 'any_key', $default );
		$this->assertSame( $default, $result );
	}

	// -----------------------------------------------------------------------
	// get_setting — tables exist, value present
	// -----------------------------------------------------------------------

	public function test_get_setting_decodes_json_array(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );

		$stored = json_encode( [ 'layout' => 'cards', 'shadow' => 'md' ] );
		$wpdb->set_get_var_result( $stored );

		$result = Settings_Repository::get_setting( Settings_Repository::KEY_GLOBAL_STYLES, [] );

		$this->assertIsArray( $result );
		$this->assertSame( 'cards', $result['layout'] );
		$this->assertSame( 'md', $result['shadow'] );
	}

	public function test_get_setting_returns_plain_string_as_is(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );

		// A plain non-JSON string should be returned without decoding.
		$wpdb->set_get_var_result( 'minimal' );

		$result = Settings_Repository::get_setting( Settings_Repository::KEY_ACTIVE_PRESET );

		$this->assertSame( 'minimal', $result );
	}

	public function test_get_setting_returns_default_when_value_is_null(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );

		// get_var returns null → treat as not found.
		$wpdb->set_get_var_result( null );

		$result = Settings_Repository::get_setting( 'missing_key', 'fallback' );
		$this->assertSame( 'fallback', $result );
	}

	// -----------------------------------------------------------------------
	// setting_exists
	// -----------------------------------------------------------------------

	public function test_setting_exists_returns_false_when_tables_missing(): void {
		$this->assertFalse( Settings_Repository::setting_exists( 'global_styles' ) );
	}

	public function test_setting_exists_returns_false_when_id_is_null(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );
		$wpdb->set_get_var_result( null ); // no row found

		$this->assertFalse( Settings_Repository::setting_exists( 'nonexistent' ) );
	}

	public function test_setting_exists_returns_true_when_id_found(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );
		$wpdb->set_get_var_result( '42' ); // row ID returned

		$this->assertTrue( Settings_Repository::setting_exists( 'global_styles' ) );
	}

	// -----------------------------------------------------------------------
	// get_typed_setting — type casting
	// -----------------------------------------------------------------------

	public function test_get_typed_setting_casts_to_int(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );
		// Store '7' — get_setting() returns the raw string, then (int)'7' = 7.
		$wpdb->set_get_var_result( '7' );

		$result = Settings_Repository::get_typed_setting( 'some_key', 'int', 0 );
		$this->assertSame( 7, $result );
		$this->assertIsInt( $result );
	}

	public function test_get_typed_setting_casts_to_bool_true(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );
		// Any non-empty, non-'0' string → (bool) = true.
		$wpdb->set_get_var_result( 'true' );

		$result = Settings_Repository::get_typed_setting( 'some_key', 'bool', false );
		$this->assertTrue( $result );
	}

	public function test_get_typed_setting_casts_to_bool_false(): void {
		global $wpdb;
		$wpdb = new MockWpdb( true );
		// PHP casts the string '0' to false; 'false' would cast to true (non-empty).
		$wpdb->set_get_var_result( '0' );

		$result = Settings_Repository::get_typed_setting( 'some_key', 'bool', true );
		$this->assertFalse( $result );
	}

	public function test_get_typed_setting_returns_typed_default_when_tables_missing(): void {
		// Tables missing (default setUp MockWpdb(false)) → get_setting returns default.
		// get_typed_setting signature: ($key, $type, $default).
		$result = Settings_Repository::get_typed_setting( 'some_key', 'int', 99 );
		$this->assertSame( 99, $result );
	}
}
