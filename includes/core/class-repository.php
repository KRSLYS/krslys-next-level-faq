<?php
/**
 * Data access layer for FAQ items stored in a custom table.
 *
 * @package Krslys\NextLevelFaq
 */

namespace Krslys\NextLevelFaq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access layer for FAQ items stored in a custom table.
 *
 * SECURITY: All database operations use $wpdb->prepare() for dynamic values.
 * Table and column names are escaped using esc_sql() where needed.
 */
class Repository {

	/**
	 * Get table name.
	 *
	 * SECURITY: Returns sanitized table name with proper prefix.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'nlf_faq_items';
	}

	/**
	 * Get all FAQ items for a given group (any status) ordered by position/created date.
	 *
	 * SECURITY: Uses $wpdb->prepare() for group_id parameter.
	 *
	 * @param int $group_id Group ID (must be > 0).
	 * @return array
	 */
	public static function get_all_items( $group_id ) {
		global $wpdb;

		if ( $group_id <= 0 ) {
			return array();
		}

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE group_id = %d ORDER BY position ASC, created_at ASC",
			(int) $group_id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is safe, query is prepared. Custom table.
		return $wpdb->get_results( $sql );
	}

	/**
	 * Retrieve FAQ records for export routines.
	 *
	 * SECURITY: Uses $wpdb->prepare() for optional group_id filter.
	 *
	 * @param int|null $group_id Optional group filter (null = all groups, must be > 0 if specified).
	 * @return array[]
	 */
	public static function get_all_items_for_export( $group_id = null ) {
		global $wpdb;

		$table = self::get_table_name();

		$where_sql = '';

		if ( null !== $group_id ) {
			$group_id = (int) $group_id;
			if ( $group_id <= 0 ) {
				return array();
			}
			$where_sql = $wpdb->prepare( 'WHERE group_id = %d', $group_id );
		} else {
			// Exclude legacy group_id = 0
			$where_sql = 'WHERE group_id > 0';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is safe, WHERE clause prepared above. Custom table.
		$rows = $wpdb->get_results(
			"SELECT group_id, position, question, answer, status, initial_state, highlight FROM {$table} {$where_sql} ORDER BY group_id ASC, position ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( $row ) {
				return array(
					'group_id'      => isset( $row['group_id'] ) ? (int) $row['group_id'] : 0,
					'position'      => isset( $row['position'] ) ? (int) $row['position'] : 0,
					'question'      => isset( $row['question'] ) ? (string) $row['question'] : '',
					'answer'        => isset( $row['answer'] ) ? (string) $row['answer'] : '',
					'status'        => isset( $row['status'] ) ? (int) $row['status'] : 0,
					'initial_state' => isset( $row['initial_state'] ) ? (int) $row['initial_state'] : 0,
					'highlight'     => isset( $row['highlight'] ) ? (int) $row['highlight'] : 0,
				);
			},
			$rows
		);
	}

	/**
	 * Get all published FAQs for a group.
	 *
	 * SECURITY: Uses $wpdb->prepare() for parameters.
	 *
	 * @param int $group_id Group ID (must be > 0).
	 * @return array
	 */
	public static function get_all_published_faqs( $group_id ) {
		global $wpdb;

		if ( $group_id <= 0 ) {
			return array();
		}

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = 1 AND group_id = %d ORDER BY position ASC, created_at ASC",
			(int) $group_id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is safe, query is prepared. Custom table.
		return $wpdb->get_results( $sql );
	}

	/**
	 * Save or update an item by ID (used by repeater UIs).
	 *
	 * SECURITY:
	 * - All inputs type-cast and sanitized.
	 * - Uses $wpdb->insert() / $wpdb->update() with format arrays.
	 *
	 * @param int    $id            Existing item ID, or 0 for insert.
	 * @param int    $group_id      Group ID (0 for global / legacy).
	 * @param string $question      Question text.
	 * @param string $answer        Answer HTML.
	 * @param int    $status        Status flag (1 = visible, 0 = hidden).
	 * @param int    $position      Item order position.
	 * @param int    $initial_state 1 = open by default, 0 = closed.
	 * @param int    $highlight     1 = highlighted, 0 = normal.
	 *
	 * @return int Inserted/updated ID.
	 */
	public static function save_item( $id, $group_id, $question, $answer, $status, $position, $initial_state = 0, $highlight = 0 ) {
		global $wpdb;

		$table = self::get_table_name();

		$data = array(
			'post_id'       => 0,
			'group_id'      => max( 0, (int) $group_id ),
			'position'      => max( 0, (int) $position ),
			'question'      => wp_kses_post( $question ),
			'answer'        => wp_kses_post( $answer ),
			'status'        => (int) $status,
			'initial_state' => (int) $initial_state,
			'highlight'     => (int) $highlight,
		);

		$format = array( '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d' );

		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$wpdb->update(
				$table,
				$data,
				array( 'id' => (int) $id ),
				$format,
				array( '%d' )
			);

			return (int) $id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
		$wpdb->insert( $table, $data, $format );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete all items except the specified IDs.
	 *
	 * SECURITY:
	 * - All IDs sanitized via array_map with intval.
	 * - Placeholders used for IN clause.
	 * - Uses $wpdb->prepare() with dynamic placeholder count.
	 *
	 * @param int[] $keep_ids IDs to keep.
	 * @param int   $group_id Group ID scope (must be > 0).
	 */
	public static function delete_all_except( $keep_ids, $group_id ) {
		global $wpdb;

		if ( $group_id <= 0 ) {
			return;
		}

		$table = self::get_table_name();

		$keep_ids = array_filter(
			array_map( 'intval', (array) $keep_ids ),
			function ( $id ) {
				return $id > 0;
			}
		);

		if ( empty( $keep_ids ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE group_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
					(int) $group_id
				)
			);
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $keep_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE group_id = %d AND id NOT IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and placeholders are safe.
				array_merge( array( (int) $group_id ), $keep_ids )
			)
		);
	}

	/**
	 * Get items for a specific group.
	 *
	 * SECURITY: Uses $wpdb->prepare() for parameters.
	 *
	 * @param int  $group_id     Group ID.
	 * @param bool $only_visible Whether to include only visible items.
	 *
	 * @return array
	 */
	public static function get_items_for_group( $group_id, $only_visible = true ) {
		global $wpdb;

	$table = self::get_table_name();

	$visible_clause = $only_visible ? 'AND status = 1' : '';

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE group_id = %d {$visible_clause} ORDER BY position ASC, created_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and visible_clause are safe.
			(int) $group_id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Query is prepared above. Custom table.
		return $wpdb->get_results( $sql );
	}

	/**
	 * Delete all items for a specific group.
	 *
	 * SECURITY: Uses $wpdb->delete() with format specifier.
	 *
	 * @param int $group_id Group ID.
	 */
	public static function delete_items_for_group( $group_id ) {
		global $wpdb;

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->delete(
			$table,
			array( 'group_id' => (int) $group_id ),
			array( '%d' )
		);
	}

	/**
	 * Delete every FAQ record.
	 *
	 * SECURITY: Uses TRUNCATE which is safe for complete table clearing.
	 * Note: TRUNCATE cannot be prepared, but it has no user input.
	 *
	 * @return void
	 */
	public static function delete_all_items() {
		global $wpdb;

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is safe, no user input. Custom table.
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

}
