<?php
/**
 * Repository CRUD: known input → expected output.
 */

namespace Krslys\NextLevelFaqAccordion\Tests\Unit;

use Brain\Monkey\Functions;
use Krslys\NextLevelFaqAccordion\Repository;
use Krslys\NextLevelFaqAccordion\Tests\MockWpdb;
use Krslys\NextLevelFaqAccordion\Tests\WpTestCase;

class RepositoryTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb = new MockWpdb( true );
		Functions\when( 'wp_kses_post' )->returnArg();
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = null;
		parent::tearDown();
	}

	// -- Table name --

	public function test_table_name(): void {
		global $wpdb;
		$this->assertSame( $wpdb->prefix . 'krslys_nlfa_items', Repository::get_table_name() );
	}

	// -- Invalid input returns empty --

	public function test_zero_group_returns_empty(): void {
		$this->assertSame( [], Repository::get_all_items( 0 ) );
	}

	public function test_negative_group_returns_empty(): void {
		$this->assertSame( [], Repository::get_all_items( -1 ) );
	}

	// -- Valid input returns data --

	public function test_valid_group_returns_rows(): void {
		global $wpdb;

		$row = new \stdClass();
		$row->id       = 1;
		$row->question = 'What is this?';
		$row->answer   = 'A plugin.';
		$wpdb->set_get_results_result( [ $row ] );

		$result = Repository::get_all_items( 5 );

		$this->assertCount( 1, $result );
		$this->assertSame( 'What is this?', $result[0]->question );
	}

	// -- Save: insert returns new ID --

	public function test_insert_returns_new_id(): void {
		global $wpdb;
		$wpdb->insert_id = 42;

		$id = Repository::save_item( 0, 1, 'New Q?', 'New A.', 1, 0 );

		$this->assertSame( 42, $id );
	}

	// -- Save: update returns same ID --

	public function test_update_returns_same_id(): void {
		$id = Repository::save_item( 5, 1, 'Updated Q', 'Updated A', 1, 0 );

		$this->assertSame( 5, $id );
	}

	// -- Export normalizes types --

	public function test_export_casts_integers(): void {
		global $wpdb;
		$wpdb->set_get_results_result( [
			[ 'group_id' => '1', 'position' => '0', 'question' => 'Q', 'answer' => 'A', 'status' => '1', 'initial_state' => '0', 'highlight' => '0' ],
		] );

		$result = Repository::get_all_items_for_export( 1 );

		$this->assertIsInt( $result[0]['group_id'] );
		$this->assertIsInt( $result[0]['status'] );
		$this->assertSame( 'Q', $result[0]['question'] );
	}
}
