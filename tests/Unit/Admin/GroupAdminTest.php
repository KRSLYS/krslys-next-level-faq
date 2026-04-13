<?php
/**
 * Tests for the Group_Admin class.
 *
 * The AJAX handler (handle_ajax_save_group) calls wp_send_json_error/success
 * which invoke die(), so we test the security and validation logic by
 * capturing which JSON response function is called first via Brain Monkey
 * expectations, then throwing a controlled exception to halt execution.
 *
 * @package Krslys\NextLevelFaqAccordion\Tests\Unit\Admin
 */

namespace Krslys\NextLevelFaqAccordion\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Krslys\NextLevelFaqAccordion\Group_Admin;
use Krslys\NextLevelFaqAccordion\Tests\MockWpdb;
use Krslys\NextLevelFaqAccordion\Tests\WpTestCase;

/**
 * Custom exception used to intercept wp_send_json_error/success calls.
 */
class AjaxExitException extends \RuntimeException {
	public string $type;
	public $payload;
	public int $http_status;

	public function __construct( string $type, $payload, int $http_status = 200 ) {
		$this->type        = $type;
		$this->payload     = $payload;
		$this->http_status = $http_status;
		parent::__construct( "AJAX {$type}" );
	}
}

/**
 * @covers \Krslys\NextLevelFaqAccordion\Group_Admin
 */
class GroupAdminTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb = new MockWpdb( true );

		// Reset superglobals.
		$_POST = array();

		// Stub common WP functions used inside the handler.
		Functions\when( 'sanitize_text_field' )->alias( function ( $str ) {
			return trim( strip_tags( (string) $str ) );
		} );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'absint' )->alias( function ( $val ) {
			return abs( (int) $val );
		} );
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		Functions\when( 'admin_url' )->alias( function ( $path = '' ) {
			return 'http://example.com/wp-admin/' . $path;
		} );
		Functions\when( 'add_query_arg' )->alias( function ( $args, $url = '' ) {
			return $url . '?' . http_build_query( $args );
		} );

		// wp_send_json_error throws exception to halt execution.
		Functions\when( 'wp_send_json_error' )->alias( function ( $data = null, $status = 200 ) {
			throw new AjaxExitException( 'error', $data, $status );
		} );

		// wp_send_json_success throws exception to halt execution.
		Functions\when( 'wp_send_json_success' )->alias( function ( $data = null, $status = 200 ) {
			throw new AjaxExitException( 'success', $data, $status );
		} );
	}

	protected function tearDown(): void {
		$_POST = array();

		global $wpdb;
		$wpdb = null;

		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Security: nonce verification
	// -----------------------------------------------------------------------

	public function test_rejects_request_without_nonce(): void {
		// No nonce in $_POST at all.
		$_POST = array(
			'nlf_group_title' => 'Test',
		);

		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		try {
			Group_Admin::handle_ajax_save_group();
			$this->fail( 'Expected AjaxExitException' );
		} catch ( AjaxExitException $e ) {
			$this->assertSame( 'error', $e->type );
			$this->assertSame( 403, $e->http_status );
			$this->assertStringContainsString( 'Security check failed', $e->payload['message'] );
		}
	}

	public function test_rejects_request_with_invalid_nonce(): void {
		$_POST = array(
			'nlf_faq_group_nonce' => 'bad-nonce',
			'nlf_group_title'     => 'Test',
		);

		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		try {
			Group_Admin::handle_ajax_save_group();
			$this->fail( 'Expected AjaxExitException' );
		} catch ( AjaxExitException $e ) {
			$this->assertSame( 'error', $e->type );
			$this->assertSame( 403, $e->http_status );
		}
	}

	// -----------------------------------------------------------------------
	// Security: capability check
	// -----------------------------------------------------------------------

	public function test_rejects_request_without_manage_options_capability(): void {
		$_POST = array(
			'nlf_faq_group_nonce' => 'valid-nonce',
			'nlf_group_title'     => 'Test',
		);

		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( false );

		try {
			Group_Admin::handle_ajax_save_group();
			$this->fail( 'Expected AjaxExitException' );
		} catch ( AjaxExitException $e ) {
			$this->assertSame( 'error', $e->type );
			$this->assertSame( 403, $e->http_status );
			$this->assertStringContainsString( 'permission', $e->payload['message'] );
		}
	}

	// -----------------------------------------------------------------------
	// Validation: empty title
	// -----------------------------------------------------------------------

	public function test_rejects_empty_title(): void {
		$_POST = array(
			'nlf_faq_group_nonce' => 'valid-nonce',
			'group_id'            => '0',
			'nlf_group_title'     => '',
		);

		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );

		try {
			Group_Admin::handle_ajax_save_group();
			$this->fail( 'Expected AjaxExitException' );
		} catch ( AjaxExitException $e ) {
			$this->assertSame( 'error', $e->type );
			$this->assertSame( 400, $e->http_status );
			$this->assertStringContainsString( 'Title is required', $e->payload['message'] );
		}
	}

	public function test_rejects_whitespace_only_title(): void {
		$_POST = array(
			'nlf_faq_group_nonce' => 'valid-nonce',
			'group_id'            => '0',
			'nlf_group_title'     => '   ',
		);

		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );

		try {
			Group_Admin::handle_ajax_save_group();
			$this->fail( 'Expected AjaxExitException' );
		} catch ( AjaxExitException $e ) {
			$this->assertSame( 'error', $e->type );
			$this->assertSame( 400, $e->http_status );
		}
	}

	// -----------------------------------------------------------------------
	// Validation: nonexistent group on update
	// -----------------------------------------------------------------------

	public function test_rejects_update_for_nonexistent_group(): void {
		$_POST = array(
			'nlf_faq_group_nonce' => 'valid-nonce',
			'group_id'            => '999',
			'nlf_group_title'     => 'Valid Title',
		);

		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'sanitize_hex_color' )->returnArg();

		// Groups_Repository::get_group_by_id returns null (not found).
		// The repository uses $wpdb->get_row which returns null by default in MockWpdb.

		try {
			Group_Admin::handle_ajax_save_group();
			$this->fail( 'Expected AjaxExitException' );
		} catch ( AjaxExitException $e ) {
			$this->assertSame( 'error', $e->type );
			$this->assertSame( 400, $e->http_status );
			$this->assertStringContainsString( 'Invalid FAQ group', $e->payload['message'] );
		}
	}

	// -----------------------------------------------------------------------
	// Happy path: successful create
	// -----------------------------------------------------------------------

	public function test_successful_create_returns_group_id(): void {
		$_POST = array(
			'nlf_faq_group_nonce'  => 'valid-nonce',
			'group_id'             => '0',
			'nlf_group_title'      => 'My FAQ Group',
			'nlf_faq_group_question' => array( 'What is FAQ?' ),
			'nlf_faq_group_answer'   => array( 'Frequently Asked Questions.' ),
			'nlf_faq_group_visible'  => array( '0' => '1' ),
		);

		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'sanitize_hex_color' )->returnArg();

		global $wpdb;
		$wpdb->insert_id = 7;

		// Stub Cache::invalidate_group dependencies.
		Functions\when( 'get_option' )->justReturn( 1 );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'delete_transient' )->justReturn( true );

		// Style_Generator::delete_group_css needs filesystem stubs.
		Functions\when( 'wp_upload_dir' )->justReturn( array(
			'basedir' => sys_get_temp_dir(),
			'baseurl' => 'http://example.com/wp-content/uploads',
		) );
		Functions\when( 'wp_mkdir_p' )->justReturn( true );

		try {
			Group_Admin::handle_ajax_save_group();
			$this->fail( 'Expected AjaxExitException' );
		} catch ( AjaxExitException $e ) {
			$this->assertSame( 'success', $e->type );
			$this->assertSame( 7, $e->payload['group_id'] );
			$this->assertStringContainsString( 'saved successfully', $e->payload['message'] );
			$this->assertArrayHasKey( 'redirect_url', $e->payload );
		}
	}
}
