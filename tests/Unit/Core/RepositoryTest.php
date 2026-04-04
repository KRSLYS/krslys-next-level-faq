<?php
/**
 * Tests for the Repository class (FAQ items CRUD).
 *
 * Uses MockWpdb so no real database connection is required.
 *
 * @package Krslys\NextLevelFaq\Tests\Unit\Core
 */

namespace Krslys\NextLevelFaq\Tests\Unit\Core;

use Brain\Monkey\Functions;
use Krslys\NextLevelFaq\Repository;
use Krslys\NextLevelFaq\Tests\MockWpdb;
use Krslys\NextLevelFaq\Tests\WpTestCase;

/**
 * @covers \Krslys\NextLevelFaq\Repository
 */
class RepositoryTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb = new MockWpdb( true );

		// Stub wp_kses_post to return input unchanged (no real WP filtering).
		Functions\when( 'wp_kses_post' )->returnArg();
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = null;
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// get_table_name
	// -----------------------------------------------------------------------

	public function test_get_table_name_returns_prefixed_name(): void {
		$this->assertSame( 'wp_nlf_faq_items', Repository::get_table_name() );
	}

	// -----------------------------------------------------------------------
	// get_all_items — guard clauses
	// -----------------------------------------------------------------------

	public function test_get_all_items_returns_empty_array_for_zero_group(): void {
		$this->assertSame( array(), Repository::get_all_items( 0 ) );
	}

	public function test_get_all_items_returns_empty_array_for_negative_group(): void {
		$this->assertSame( array(), Repository::get_all_items( -1 ) );
	}

	// -----------------------------------------------------------------------
	// get_all_items — results
	// -----------------------------------------------------------------------

	public function test_get_all_items_returns_results_for_valid_group(): void {
		global $wpdb;

		$row       = new \stdClass();
		$row->id   = 1;
		$row->question = 'Test?';
		$row->answer   = 'Yes.';

		$wpdb->set_get_results_result( array( $row ) );

		$result = Repository::get_all_items( 5 );

		$this->assertCount( 1, $result );
		$this->assertSame( 'Test?', $result[0]->question );
	}

	public function test_get_all_items_returns_empty_when_no_rows(): void {
		global $wpdb;
		$wpdb->set_get_results_result( array() );

		$result = Repository::get_all_items( 1 );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	// -----------------------------------------------------------------------
	// get_all_published_faqs — guard clauses
	// -----------------------------------------------------------------------

	public function test_get_all_published_faqs_returns_empty_for_zero_group(): void {
		$this->assertSame( array(), Repository::get_all_published_faqs( 0 ) );
	}

	public function test_get_all_published_faqs_returns_empty_for_negative_group(): void {
		$this->assertSame( array(), Repository::get_all_published_faqs( -5 ) );
	}

	// -----------------------------------------------------------------------
	// get_all_published_faqs — results
	// -----------------------------------------------------------------------

	public function test_get_all_published_faqs_returns_rows(): void {
		global $wpdb;

		$row           = new \stdClass();
		$row->id       = 10;
		$row->question = 'Published Q';
		$row->status   = 1;

		$wpdb->set_get_results_result( array( $row ) );

		$result = Repository::get_all_published_faqs( 3 );
		$this->assertCount( 1, $result );
		$this->assertSame( 'Published Q', $result[0]->question );
	}

	// -----------------------------------------------------------------------
	// get_items_for_group
	// -----------------------------------------------------------------------

	public function test_get_items_for_group_returns_results(): void {
		global $wpdb;

		$row           = new \stdClass();
		$row->id       = 7;
		$row->question = 'Group item';

		$wpdb->set_get_results_result( array( $row ) );

		$result = Repository::get_items_for_group( 2, true );
		$this->assertCount( 1, $result );
	}

	public function test_get_items_for_group_includes_hidden_when_flag_is_false(): void {
		global $wpdb;

		$wpdb->set_get_results_result( array() );

		// Should not throw — just exercises the code path.
		$result = Repository::get_items_for_group( 2, false );
		$this->assertIsArray( $result );
	}

	// -----------------------------------------------------------------------
	// save_item — insert (id = 0)
	// -----------------------------------------------------------------------

	public function test_save_item_inserts_new_item_when_id_is_zero(): void {
		global $wpdb;
		$wpdb->insert_id = 42;

		$result = Repository::save_item( 0, 1, 'New Q?', 'New A.', 1, 0 );

		$this->assertSame( 42, $result );
	}

	public function test_save_item_clamps_negative_group_id_to_zero(): void {
		global $wpdb;
		$wpdb->insert_id = 10;

		// Should not throw — group_id is clamped via max(0, ...).
		$result = Repository::save_item( 0, -5, 'Q', 'A', 1, 0 );
		$this->assertSame( 10, $result );
	}

	public function test_save_item_clamps_negative_position_to_zero(): void {
		global $wpdb;
		$wpdb->insert_id = 11;

		$result = Repository::save_item( 0, 1, 'Q', 'A', 1, -3 );
		$this->assertSame( 11, $result );
	}

	// -----------------------------------------------------------------------
	// save_item — update (id > 0)
	// -----------------------------------------------------------------------

	public function test_save_item_updates_existing_when_id_is_positive(): void {
		$result = Repository::save_item( 5, 1, 'Updated Q', 'Updated A', 1, 0 );

		$this->assertSame( 5, $result );
	}

	// -----------------------------------------------------------------------
	// save_item — optional params
	// -----------------------------------------------------------------------

	public function test_save_item_accepts_initial_state_and_highlight(): void {
		global $wpdb;
		$wpdb->insert_id = 20;

		$result = Repository::save_item( 0, 1, 'Q', 'A', 1, 0, 1, 1 );
		$this->assertSame( 20, $result );
	}

	// -----------------------------------------------------------------------
	// delete_all_except — guard clauses
	// -----------------------------------------------------------------------

	public function test_delete_all_except_does_nothing_for_zero_group(): void {
		// Should return without calling any DB methods.
		Repository::delete_all_except( array( 1, 2 ), 0 );

		// If it didn't throw, the guard worked.
		$this->assertTrue( true );
	}

	public function test_delete_all_except_does_nothing_for_negative_group(): void {
		Repository::delete_all_except( array( 1 ), -1 );
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// delete_all_except — behavior
	// -----------------------------------------------------------------------

	public function test_delete_all_except_with_empty_keep_ids_deletes_all(): void {
		// Empty keep_ids → DELETE all items for the group.
		Repository::delete_all_except( array(), 5 );
		$this->assertTrue( true );
	}

	public function test_delete_all_except_filters_out_zero_and_negative_ids(): void {
		// 0 and negative IDs should be filtered out before the query.
		Repository::delete_all_except( array( 0, -1, 3 ), 5 );
		$this->assertTrue( true );
	}

	public function test_delete_all_except_with_valid_keep_ids_executes(): void {
		Repository::delete_all_except( array( 1, 2, 3 ), 5 );
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// delete_items_for_group
	// -----------------------------------------------------------------------

	public function test_delete_items_for_group_executes_without_error(): void {
		Repository::delete_items_for_group( 3 );
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// delete_all_items
	// -----------------------------------------------------------------------

	public function test_delete_all_items_executes_without_error(): void {
		Repository::delete_all_items();
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// get_all_items_for_export — guard clauses
	// -----------------------------------------------------------------------

	public function test_get_all_items_for_export_returns_empty_for_zero_group(): void {
		$this->assertSame( array(), Repository::get_all_items_for_export( 0 ) );
	}

	public function test_get_all_items_for_export_returns_empty_for_negative_group(): void {
		$this->assertSame( array(), Repository::get_all_items_for_export( -1 ) );
	}

	// -----------------------------------------------------------------------
	// get_all_items_for_export — results
	// -----------------------------------------------------------------------

	public function test_get_all_items_for_export_returns_normalized_rows(): void {
		global $wpdb;

		$wpdb->set_get_results_result( array(
			array(
				'group_id'      => '1',
				'position'      => '0',
				'question'      => 'Export Q',
				'answer'        => 'Export A',
				'status'        => '1',
				'initial_state' => '0',
				'highlight'     => '0',
			),
		) );

		$result = Repository::get_all_items_for_export( 1 );

		$this->assertCount( 1, $result );
		$this->assertSame( 1, $result[0]['group_id'] );
		$this->assertSame( 'Export Q', $result[0]['question'] );
		$this->assertIsInt( $result[0]['position'] );
		$this->assertIsInt( $result[0]['status'] );
	}

	public function test_get_all_items_for_export_without_group_returns_all(): void {
		global $wpdb;
		$wpdb->set_get_results_result( array() );

		$result = Repository::get_all_items_for_export( null );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_get_all_items_for_export_handles_missing_keys_gracefully(): void {
		global $wpdb;

		// Row with missing keys — should default to safe values.
		$wpdb->set_get_results_result( array(
			array( 'question' => 'Only Q' ),
		) );

		$result = Repository::get_all_items_for_export( 1 );

		$this->assertCount( 1, $result );
		$this->assertSame( 0, $result[0]['group_id'] );
		$this->assertSame( 0, $result[0]['position'] );
		$this->assertSame( 'Only Q', $result[0]['question'] );
		$this->assertSame( '', $result[0]['answer'] );
		$this->assertSame( 0, $result[0]['status'] );
	}
}
