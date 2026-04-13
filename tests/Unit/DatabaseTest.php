<?php
/**
 * Database table names are correct.
 */

namespace Krslys\NextLevelFaqAccordion\Tests\Unit;

use Krslys\NextLevelFaqAccordion\Database;
use Krslys\NextLevelFaqAccordion\Tests\MockWpdb;
use Krslys\NextLevelFaqAccordion\Tests\WpTestCase;

class DatabaseTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb = new MockWpdb( true );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = null;
		parent::tearDown();
	}

	public function test_groups_table_name(): void {
		$this->assertSame( 'wp_krslys_nlfa_groups', Database::get_groups_table() );
	}

	public function test_items_table_name(): void {
		$this->assertSame( 'wp_krslys_nlfa_items', Database::get_items_table() );
	}

	public function test_settings_table_name(): void {
		$this->assertSame( 'wp_krslys_nlfa_settings', Database::get_settings_table() );
	}

	public function test_schema_version_is_defined(): void {
		$this->assertNotEmpty( Database::SCHEMA_VERSION );
	}
}
