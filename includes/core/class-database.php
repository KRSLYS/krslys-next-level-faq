<?php
/**
 * Database schema manager for custom tables.
 *
 * @package Krslys\NextLevelFaqAccordion
 */

namespace Krslys\NextLevelFaqAccordion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database schema manager.
 *
 * Handles creation, versioning, and cleanup of custom database tables.
 * Uses WordPress dbDelta() for safe schema management.
 */
class Database {

	/**
	 * Schema version constant.
	 */
	const SCHEMA_VERSION = '1.0.0';

	/**
	 * Register database-related hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'create_tables' ) );
	}

	/**
	 * Get the groups table name with prefix.
	 *
	 * @return string
	 */
	public static function get_groups_table() {
		global $wpdb;
		return $wpdb->prefix . 'krslys_nlfa_groups';
	}

	/**
	 * Get the items table name with prefix.
	 *
	 * @return string
	 */
	public static function get_items_table() {
		global $wpdb;
		return $wpdb->prefix . 'krslys_nlfa_items';
	}

	/**
	 * Get the settings table name with prefix.
	 *
	 * @return string
	 */
	public static function get_settings_table() {
		global $wpdb;
		return $wpdb->prefix . 'krslys_nlfa_settings';
	}

	/**
	 * Create or update all custom tables.
	 *
	 * Called on plugin activation and when schema version changes.
	 * 
	 * @param bool $force Force creation even if version is up to date.
	 */
	public static function create_tables( $force = false ) {
		$current_version = get_option( 'krslys_nlfa_schema_version', '0.0.0' );

		// Only run if schema version changed, forced, or migration needed.
		if ( ! $force && version_compare( $current_version, self::SCHEMA_VERSION, '>=' ) && ! self::needs_migration() ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Create groups table
		self::create_groups_table( $charset_collate );

		// Update items table (keep existing structure, add any new columns if needed)
		self::update_items_table( $charset_collate );

		// Create settings table
		self::create_settings_table( $charset_collate );

		// Update schema version
		update_option( 'krslys_nlfa_schema_version', self::SCHEMA_VERSION );
	}

	/**
	 * Create the FAQ groups table.
	 *
	 * @param string $charset_collate Charset collation string.
	 */
	private static function create_groups_table( $charset_collate ) {
		$table_name = self::get_groups_table();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL DEFAULT '',
			slug varchar(200) NOT NULL DEFAULT '',
			description text,
			theme_settings longtext,
			display_settings longtext,
			custom_styles longtext,
			use_custom_style tinyint(1) NOT NULL DEFAULT 0,
			type varchar(20) NOT NULL DEFAULT 'faq',
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY type (type),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Update the FAQ items table (keep existing, ensure proper structure).
	 *
	 * @param string $charset_collate Charset collation string.
	 */
	private static function update_items_table( $charset_collate ) {
		$table_name = self::get_items_table();

		// Keep the existing table structure from Repository class
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			group_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			position int(11) UNSIGNED NOT NULL DEFAULT 0,
			question text NOT NULL,
			answer longtext NOT NULL,
			status tinyint(1) NOT NULL DEFAULT 0,
			initial_state tinyint(1) NOT NULL DEFAULT 0,
			highlight tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY group_id (group_id),
			KEY position (position)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create the plugin settings table.
	 *
	 * @param string $charset_collate Charset collation string.
	 */
	private static function create_settings_table( $charset_collate ) {
		$table_name = self::get_settings_table();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			setting_key varchar(100) NOT NULL DEFAULT '',
			setting_value longtext NOT NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY setting_key (setting_key)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop all custom tables.
	 *
	 * Only called on plugin uninstall.
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			self::get_groups_table(),
			self::get_items_table(),
			self::get_settings_table(),
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL on custom tables during uninstall.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		// Delete schema version
		delete_option( 'krslys_nlfa_schema_version' );
	}

	/**
	 * Check if all tables exist.
	 *
	 * @return bool True if all tables exist.
	 */
	public static function tables_exist() {
		global $wpdb;

		$tables = array(
			self::get_groups_table(),
			self::get_items_table(),
			self::get_settings_table(),
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Checking existence of custom tables.
			$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
			if ( $exists !== $table ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if a schema migration is needed (e.g. new columns).
	 *
	 * @return bool
	 */
	private static function needs_migration() {
		global $wpdb;

		$table = self::get_groups_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema introspection on custom table.
		$column = $wpdb->get_var( "SHOW COLUMNS FROM `{$table}` LIKE 'type'" );

		return empty( $column );
	}

	/**
	 * Get current schema version.
	 *
	 * @return string
	 */
	public static function get_schema_version() {
		return get_option( 'krslys_nlfa_schema_version', '0.0.0' );
	}
}

