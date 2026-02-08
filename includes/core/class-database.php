<?php
/**
 * Database schema manager for custom tables.
 *
 * @package Krslys\NextLevelFaq
 */

namespace Krslys\NextLevelFaq;

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
	const SCHEMA_VERSION = '2.0.0';

	/**
	 * Get the groups table name with prefix.
	 *
	 * @return string
	 */
	public static function get_groups_table() {
		global $wpdb;
		return $wpdb->prefix . 'nlf_faq_groups';
	}

	/**
	 * Get the items table name with prefix.
	 *
	 * @return string
	 */
	public static function get_items_table() {
		global $wpdb;
		return $wpdb->prefix . 'nlf_faq_items';
	}

	/**
	 * Get the settings table name with prefix.
	 *
	 * @return string
	 */
	public static function get_settings_table() {
		global $wpdb;
		return $wpdb->prefix . 'nlf_plugin_settings';
	}

	/**
	 * Create or update all custom tables.
	 *
	 * Called on plugin activation and when schema version changes.
	 * 
	 * @param bool $force Force creation even if version is up to date.
	 */
	public static function create_tables( $force = false ) {
		$current_version = get_option( 'nlf_faq_schema_version', '0.0.0' );

		// Only run if schema version changed or forced
		if ( ! $force && version_compare( $current_version, self::SCHEMA_VERSION, '>=' ) ) {
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
		update_option( 'nlf_faq_schema_version', self::SCHEMA_VERSION );
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
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
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
	 * Clean up legacy data from WordPress core tables.
	 *
	 * Removes old Custom Post Type posts, postmeta, and legacy options.
	 * This is safe to run - it only removes plugin-specific data.
	 */
	public static function cleanup_legacy_data() {
		global $wpdb;

		// Delete old CPT posts (nlf_faq_group)
		$deleted_posts = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->posts} WHERE post_type = %s",
				'nlf_faq_group'
			)
		);

		// Delete orphaned postmeta (postmeta without corresponding post)
		$wpdb->query(
			"DELETE pm FROM {$wpdb->postmeta} pm 
			LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
			WHERE p.ID IS NULL"
		);

		// Delete old style options (will be migrated to settings table)
		delete_option( 'nlf_faq_style_options' );
		delete_option( 'nlf_faq_db_version' );

		// Clear legacy items with group_id = 0 (old questions feature)
		$items_table = self::get_items_table();
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$items_table} WHERE group_id = %d",
				0
			)
		);

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
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		// Delete schema version
		delete_option( 'nlf_faq_schema_version' );
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
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
			if ( $exists !== $table ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get current schema version.
	 *
	 * @return string
	 */
	public static function get_schema_version() {
		return get_option( 'nlf_faq_schema_version', '0.0.0' );
	}
}

