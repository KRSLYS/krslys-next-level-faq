<?php
/**
 * Repository for plugin settings stored as JSON.
 *
 * @package Krslys\NextLevelFaqAccordion
 */

namespace Krslys\NextLevelFaqAccordion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Repository class.
 *
 * Manages plugin-wide settings stored as JSON in custom table.
 * Provides a simple key-value interface with JSON storage.
 */
class Settings_Repository {

	/**
	 * Setting key for global styles.
	 */
	const KEY_GLOBAL_STYLES = 'global_styles';

	/**
	 * Setting key for active preset.
	 */
	const KEY_ACTIVE_PRESET = 'active_preset';

	/**
	 * Setting key for cache configuration.
	 */
	const KEY_CACHE_CONFIG = 'cache_config';

	/**
	 * Setting key for schema markup toggle.
	 */
	const KEY_SCHEMA_MARKUP = 'enable_schema_markup';

	/**
	 * Get a setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value if setting not found.
	 * @return mixed Setting value or default.
	 */
	public static function get_setting( $key, $default = null ) {
		global $wpdb;
		
		// Ensure tables exist
		if ( ! Database::tables_exist() ) {
			return $default;
		}
		
		$table = Database::get_settings_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$sql = $wpdb->prepare(
			"SELECT setting_value FROM {$table} WHERE setting_key = %s",
			sanitize_key( $key )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, query prepared above.
		$value = $wpdb->get_var( $sql );

		if ( null === $value ) {
			return $default;
		}

		// Decode JSON
		$decoded = json_decode( $value, true );

		// Return decoded value if valid JSON, otherwise the raw string.
		if ( null === $decoded && 'null' !== strtolower( trim( $value ) ) ) {
			// json_decode failed — return raw string.
			return $value;
		}

		return $decoded;
	}

	/**
	 * Update or create a setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value (will be JSON encoded).
	 * @return bool True on success, false on failure.
	 */
	public static function update_setting( $key, $value ) {
		global $wpdb;
		
		// Ensure tables exist
		if ( ! Database::tables_exist() ) {
			Database::create_tables( true ); // Force creation
		}
		
		$table = Database::get_settings_table();

		$key = sanitize_key( $key );

		// Encode value as JSON
		$json_value = is_string( $value ) && ! is_numeric( $value ) ? $value : wp_json_encode( $value );

		// Check if setting exists
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE setting_key = %s",
				$key
			)
		);

		if ( $exists ) {
			// Update existing setting
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$result = $wpdb->update(
				$table,
				array( 'setting_value' => $json_value ),
				array( 'setting_key' => $key ),
				array( '%s' ),
				array( '%s' )
			);
			
			// For updates, wpdb->update returns:
			// - false on error
			// - 0 if no rows changed (value is same) - this is SUCCESS
			// - number of rows updated (usually 1) - this is SUCCESS
			// So we only fail if it's exactly false
			if ( false === $result ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging behind WP_DEBUG check.
					error_log( 'Settings_Repository: Update failed. Error: ' . $wpdb->last_error );
				}
				return false;
			}
			return true;
		} else {
			// Insert new setting.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
			$result = $wpdb->insert(
				$table,
				array(
					'setting_key'   => $key,
					'setting_value' => $json_value,
				),
				array( '%s', '%s' )
			);

			if ( false === $result ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging behind WP_DEBUG check.
					error_log( 'Settings_Repository: Insert failed. Error: ' . $wpdb->last_error );
				}
				return false;
			}
			return true;
		}
	}

	/**
	 * Delete a setting.
	 *
	 * @param string $key Setting key.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_setting( $key ) {
		global $wpdb;
		$table = Database::get_settings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$result = $wpdb->delete(
			$table,
			array( 'setting_key' => sanitize_key( $key ) ),
			array( '%s' )
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Get all settings as associative array.
	 *
	 * @return array All settings keyed by setting_key.
	 */
	public static function get_all_settings() {
		global $wpdb;
		$table = Database::get_settings_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$rows = $wpdb->get_results(
			"SELECT setting_key, setting_value FROM {$table}",
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$settings = array();

		foreach ( $rows as $row ) {
			$key = $row['setting_key'];
			$value = $row['setting_value'];

			// Decode JSON
			$decoded = json_decode( $value, true );
			$settings[ $key ] = is_array( $decoded ) || is_object( $decoded ) ? $decoded : $value;
		}

		return $settings;
	}

	/**
	 * Check if a setting exists.
	 *
	 * @param string $key Setting key.
	 * @return bool True if exists, false otherwise.
	 */
	public static function setting_exists( $key ) {
		global $wpdb;
		$table = Database::get_settings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT id FROM {$table} WHERE setting_key = %s",
				sanitize_key( $key )
			)
		);

		return null !== $exists;
	}

	/**
	 * Initialize default settings on activation.
	 *
	 * Only sets defaults if settings don't already exist.
	 */
	public static function initialize_defaults() {
		// Initialize global styles if not exist
		if ( ! self::setting_exists( self::KEY_GLOBAL_STYLES ) ) {
			$defaults = Options::get_defaults();
			self::update_setting( self::KEY_GLOBAL_STYLES, $defaults );
		}

		// Initialize active preset if not exist
		if ( ! self::setting_exists( self::KEY_ACTIVE_PRESET ) ) {
			self::update_setting( self::KEY_ACTIVE_PRESET, Options::get_default_preset_slug() );
		}

		// Initialize cache config if not exist
		if ( ! self::setting_exists( self::KEY_CACHE_CONFIG ) ) {
			self::update_setting(
				self::KEY_CACHE_CONFIG,
				array(
					'enabled' => true,
					'ttl'     => 3600,
				)
			);
		}

		// Initialize schema markup if not exist
		if ( ! self::setting_exists( self::KEY_SCHEMA_MARKUP ) ) {
			self::update_setting( self::KEY_SCHEMA_MARKUP, true );
		}
	}

	/**
	 * Bulk update multiple settings.
	 *
	 * @param array $settings Associative array of key => value pairs.
	 * @return bool True if all updates succeeded, false otherwise.
	 */
	public static function bulk_update( $settings ) {
		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return false;
		}

		$success = true;

		foreach ( $settings as $key => $value ) {
			if ( ! self::update_setting( $key, $value ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Clear all settings (use with caution).
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function clear_all() {
		global $wpdb;
		$table = Database::get_settings_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$result = $wpdb->query( "TRUNCATE TABLE {$table}" );

		return false !== $result;
	}

	/**
	 * Export all settings as JSON string.
	 *
	 * @return string JSON encoded settings.
	 */
	public static function export_settings() {
		$settings = self::get_all_settings();
		return wp_json_encode( $settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Import settings from JSON string.
	 *
	 * @param string $json JSON encoded settings.
	 * @param bool   $replace Whether to replace existing settings.
	 * @return bool True on success, false on failure.
	 */
	public static function import_settings( $json, $replace = false ) {
		$settings = json_decode( $json, true );

		if ( ! is_array( $settings ) ) {
			return false;
		}

		if ( $replace ) {
			self::clear_all();
		}

		return self::bulk_update( $settings );
	}

	/**
	 * Get setting with type casting.
	 *
	 * @param string $key Setting key.
	 * @param string $type Expected type (string, int, bool, array).
	 * @param mixed  $default Default value.
	 * @return mixed Type-casted value.
	 */
	public static function get_typed_setting( $key, $type, $default = null ) {
		$value = self::get_setting( $key, $default );

		switch ( $type ) {
			case 'int':
			case 'integer':
				return (int) $value;
			case 'bool':
			case 'boolean':
				return (bool) $value;
			case 'string':
				return (string) $value;
			case 'array':
				return is_array( $value ) ? $value : array();
			case 'float':
			case 'double':
				return (float) $value;
			default:
				return $value;
		}
	}
}

