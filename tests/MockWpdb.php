<?php
/**
 * Minimal $wpdb mock for unit tests.
 *
 * @package Krslys\NextLevelFaqAccordion\Tests
 */

namespace Krslys\NextLevelFaqAccordion\Tests;

/**
 * Lightweight stand-in for wpdb that avoids a real database connection.
 *
 * Usage:
 *
 *   global $wpdb;
 *   $wpdb = new MockWpdb();           // tables do NOT exist (default)
 *   $wpdb = new MockWpdb( true );     // tables exist
 *   $wpdb->set_get_var_result( 'x' ); // override the next get_var() result
 *   $wpdb->set_get_row_result( (object) [...] );
 */
class MockWpdb {

	/** @var string Table prefix. */
	public string $prefix = 'wp_';

	/** @var int Simulated last insert ID (set by insert()). */
	public int $insert_id = 0;

	/** @var bool Whether SHOW TABLES queries report tables as present. */
	private bool $tables_exist;

	/** @var mixed|null Queued result for the next get_var() call. */
	private $queued_get_var = null;

	/** @var bool Whether a queued get_var result has been set. */
	private bool $has_queued_get_var = false;

	/** @var object|null Result returned by get_row(). */
	private ?object $get_row_result = null;

	/** @var array Results returned by get_results(). */
	private array $get_results_result = [];

	/**
	 * @param bool $tables_exist Pass true to simulate all custom tables existing.
	 */
	public function __construct( bool $tables_exist = false ) {
		$this->tables_exist = $tables_exist;
	}

	// -----------------------------------------------------------------------
	// Result setters (call before the code under test runs)
	// -----------------------------------------------------------------------

	public function set_get_var_result( $value ): void {
		$this->queued_get_var     = $value;
		$this->has_queued_get_var = true;
	}

	public function set_get_row_result( ?object $value ): void {
		$this->get_row_result = $value;
	}

	public function set_get_results_result( array $value ): void {
		$this->get_results_result = $value;
	}

	// -----------------------------------------------------------------------
	// wpdb method implementations
	// -----------------------------------------------------------------------

	public function prepare( string $query, ...$args ): string {
		// Return the query as-is; prepared SQL is only passed to other mock
		// methods that ignore it anyway.
		return $query;
	}

	public function get_var( string $sql ): ?string {
		// SHOW TABLES queries always use the tables_exist flag — never the
		// queue.  This prevents tables_exist() from consuming a queued result
		// that was meant for a subsequent SELECT query.
		if ( false !== strpos( $sql, 'SHOW TABLES LIKE' ) ) {
			if ( ! $this->tables_exist ) {
				return null;
			}
			// Return the table name so the $exists !== $table check passes.
			if ( preg_match( "/SHOW TABLES LIKE '([^']+)'/i", $sql, $m ) ) {
				return $m[1];
			}
			return null;
		}

		// For all other queries, honour the manually queued result.
		if ( $this->has_queued_get_var ) {
			$result                   = $this->queued_get_var;
			$this->has_queued_get_var = false;
			$this->queued_get_var     = null;
			return $result;
		}

		return null;
	}

	public function get_row( string $sql, string $output = OBJECT ): ?object {
		return $this->get_row_result;
	}

	public function get_results( string $sql, string $output = OBJECT ): array {
		return $this->get_results_result;
	}

	public function insert( string $table, array $data, $format = null ): int {
		return 1;
	}

	public function update( string $table, array $data, array $where, $format = null, $where_format = null ): int {
		return 1;
	}

	public function delete( string $table, array $where, $where_format = null ): int {
		return 1;
	}

	public function query( string $sql ): bool {
		return true;
	}

	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}
}
