<?php
/**
 * Repository for FAQ Groups table operations.
 *
 * @package Krslys\NextLevelFaq
 */

namespace Krslys\NextLevelFaq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Groups Repository class.
 *
 * Provides CRUD operations for FAQ groups stored in custom table.
 * All methods use prepared statements for security.
 */
class Groups_Repository {

	/**
	 * Get group by ID.
	 *
	 * @param int $id Group ID.
	 * @return object|null Group object or null if not found.
	 */
	public static function get_group_by_id( $id ) {
		global $wpdb;
		
		// Ensure tables exist
		if ( ! Database::tables_exist() ) {
			return null;
		}
		
		$table = Database::get_groups_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			(int) $id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$group = $wpdb->get_row( $sql );

		if ( ! $group ) {
			return null;
		}

		return self::decode_group_json( $group );
	}

	/**
	 * Get group by slug.
	 *
	 * @param string $slug Group slug.
	 * @return object|null Group object or null if not found.
	 */
	public static function get_group_by_slug( $slug ) {
		global $wpdb;
		$table = Database::get_groups_table();

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE slug = %s",
			sanitize_title( $slug )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$group = $wpdb->get_row( $sql );

		if ( ! $group ) {
			return null;
		}

		return self::decode_group_json( $group );
	}

	/**
	 * Get all groups.
	 *
	 * @param string|null $status Optional status filter ('active', 'inactive', etc.).
	 * @param string      $orderby Order by column (default: 'created_at').
	 * @param string      $order Sort order (default: 'DESC').
	 * @return array Array of group objects.
	 */
	public static function get_all_groups( $status = null, $orderby = 'created_at', $order = 'DESC' ) {
		global $wpdb;
		$table = Database::get_groups_table();

		$where = '';
		if ( null !== $status ) {
			$where = $wpdb->prepare( 'WHERE status = %s', sanitize_key( $status ) );
		}

		// Validate orderby and order to prevent SQL injection
		$allowed_orderby = array( 'id', 'title', 'slug', 'status', 'created_at', 'updated_at' );
		$orderby = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'created_at';
		$order = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$groups = $wpdb->get_results( $sql );

		if ( empty( $groups ) ) {
			return array();
		}

		// Decode JSON fields for all groups
		return array_map( array( __CLASS__, 'decode_group_json' ), $groups );
	}

	/**
	 * Create a new group.
	 *
	 * @param array $data Group data array.
	 *                    - title (required): Group title
	 *                    - slug (optional): Auto-generated from title if not provided
	 *                    - description (optional): Group description
	 *                    - theme_settings (optional): Array of theme settings
	 *                    - display_settings (optional): Array of display settings
	 *                    - custom_styles (optional): Array of custom styles
	 *                    - use_custom_style (optional): Boolean
	 *                    - status (optional): Default 'active'
	 * @return int|false Inserted group ID or false on failure.
	 */
	public static function create_group( $data ) {
		global $wpdb;
		$table = Database::get_groups_table();

		// Validate required fields
		if ( empty( $data['title'] ) ) {
			return false;
		}

		// Generate slug if not provided
		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['title'] );
		} else {
			$data['slug'] = sanitize_title( $data['slug'] );
		}

		// Ensure slug is unique
		$data['slug'] = self::generate_unique_slug( $data['slug'] );

		// Prepare data for insertion
		$insert_data = array(
			'title'             => sanitize_text_field( $data['title'] ),
			'slug'              => $data['slug'],
			'description'       => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '',
			'theme_settings'    => isset( $data['theme_settings'] ) ? wp_json_encode( $data['theme_settings'] ) : wp_json_encode( array() ),
			'display_settings'  => isset( $data['display_settings'] ) ? wp_json_encode( $data['display_settings'] ) : wp_json_encode( array() ),
			'custom_styles'     => isset( $data['custom_styles'] ) ? wp_json_encode( $data['custom_styles'] ) : wp_json_encode( array() ),
			'use_custom_style'  => isset( $data['use_custom_style'] ) ? (int) $data['use_custom_style'] : 0,
			'status'            => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'active',
		);

		$format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

		$result = $wpdb->insert( $table, $insert_data, $format );

		if ( false === $result ) {
			return false;
		}

		Cache::invalidate_group( $wpdb->insert_id );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing group.
	 *
	 * @param int   $id Group ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public static function update_group( $id, $data ) {
		global $wpdb;
		$table = Database::get_groups_table();

		if ( $id <= 0 ) {
			return false;
		}

		// Check if group exists
		if ( ! self::get_group_by_id( $id ) ) {
			return false;
		}

		$update_data = array();
		$format = array();

		// Only update provided fields
		if ( isset( $data['title'] ) ) {
			$update_data['title'] = sanitize_text_field( $data['title'] );
			$format[] = '%s';
		}

		if ( isset( $data['slug'] ) ) {
			$slug = sanitize_title( $data['slug'] );
			// Ensure slug is unique (excluding current group)
			$slug = self::generate_unique_slug( $slug, $id );
			$update_data['slug'] = $slug;
			$format[] = '%s';
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = wp_kses_post( $data['description'] );
			$format[] = '%s';
		}

		if ( isset( $data['theme_settings'] ) ) {
			$update_data['theme_settings'] = wp_json_encode( $data['theme_settings'] );
			$format[] = '%s';
		}

		if ( isset( $data['display_settings'] ) ) {
			$update_data['display_settings'] = wp_json_encode( $data['display_settings'] );
			$format[] = '%s';
		}

		if ( isset( $data['custom_styles'] ) ) {
			$update_data['custom_styles'] = wp_json_encode( $data['custom_styles'] );
			$format[] = '%s';
		}

		if ( isset( $data['use_custom_style'] ) ) {
			$update_data['use_custom_style'] = (int) $data['use_custom_style'];
			$format[] = '%d';
		}

		if ( isset( $data['status'] ) ) {
			$update_data['status'] = sanitize_key( $data['status'] );
			$format[] = '%s';
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => (int) $id ),
			$format,
			array( '%d' )
		);

		Cache::invalidate_group( $id );

		return false !== $result;
	}

	/**
	 * Delete a group and all its items.
	 *
	 * @param int $id Group ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_group( $id ) {
		global $wpdb;

		if ( $id <= 0 ) {
			return false;
		}

		// Delete all items in this group first
		Repository::delete_items_for_group( $id );

		// Delete the group
		$table = Database::get_groups_table();
		$result = $wpdb->delete(
			$table,
			array( 'id' => (int) $id ),
			array( '%d' )
		);

		Cache::invalidate_group( $id );

		return false !== $result && $result > 0;
	}

	/**
	 * Get theme settings for a group.
	 *
	 * @param int $id Group ID.
	 * @return array Theme settings array.
	 */
	public static function get_group_theme_settings( $id ) {
		$group = self::get_group_by_id( $id );

		if ( ! $group || empty( $group->theme_settings ) ) {
			return array();
		}

		return is_array( $group->theme_settings ) ? $group->theme_settings : array();
	}

	/**
	 * Get display settings for a group.
	 *
	 * @param int $id Group ID.
	 * @return array Display settings array.
	 */
	public static function get_group_display_settings( $id ) {
		$group = self::get_group_by_id( $id );

		if ( ! $group || empty( $group->display_settings ) ) {
			return array();
		}

		return is_array( $group->display_settings ) ? $group->display_settings : array();
	}

	/**
	 * Update specific settings for a group.
	 *
	 * @param int    $id Group ID.
	 * @param string $type Settings type ('theme_settings', 'display_settings', 'custom_styles').
	 * @param array  $settings Settings array.
	 * @return bool True on success, false on failure.
	 */
	public static function update_group_settings( $id, $type, $settings ) {
		$allowed_types = array( 'theme_settings', 'display_settings', 'custom_styles' );

		if ( ! in_array( $type, $allowed_types, true ) ) {
			return false;
		}

		return self::update_group(
			$id,
			array( $type => $settings )
		);
	}

	/**
	 * Generate a unique slug.
	 *
	 * @param string   $slug Base slug.
	 * @param int|null $exclude_id Optional ID to exclude from uniqueness check.
	 * @return string Unique slug.
	 */
	private static function generate_unique_slug( $slug, $exclude_id = null ) {
		global $wpdb;
		$table = Database::get_groups_table();

		$original_slug = $slug;
		$counter = 1;

		while ( true ) {
			$where = $wpdb->prepare( 'WHERE slug = %s', $slug );

			if ( null !== $exclude_id ) {
				$where .= $wpdb->prepare( ' AND id != %d', (int) $exclude_id );
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( "SELECT id FROM {$table} {$where}" );

			if ( ! $exists ) {
				return $slug;
			}

			$slug = $original_slug . '-' . $counter;
			$counter++;
		}
	}

	/**
	 * Decode JSON fields in a group object.
	 *
	 * @param object $group Group object from database.
	 * @return object Group object with decoded JSON fields.
	 */
	private static function decode_group_json( $group ) {
		if ( ! $group ) {
			return $group;
		}

		// Decode JSON fields
		if ( ! empty( $group->theme_settings ) ) {
			$decoded = json_decode( $group->theme_settings, true );
			$group->theme_settings = is_array( $decoded ) ? $decoded : array();
		} else {
			$group->theme_settings = array();
		}

		if ( ! empty( $group->display_settings ) ) {
			$decoded = json_decode( $group->display_settings, true );
			$group->display_settings = is_array( $decoded ) ? $decoded : array();
		} else {
			$group->display_settings = array();
		}

		if ( ! empty( $group->custom_styles ) ) {
			$decoded = json_decode( $group->custom_styles, true );
			$group->custom_styles = is_array( $decoded ) ? $decoded : array();
		} else {
			$group->custom_styles = array();
		}

		// Cast boolean
		$group->use_custom_style = (bool) $group->use_custom_style;

		return $group;
	}

	/**
	 * Count total groups.
	 *
	 * @param string|null $status Optional status filter.
	 * @return int Total count.
	 */
	public static function count_groups( $status = null ) {
		global $wpdb;
		$table = Database::get_groups_table();

		if ( null !== $status ) {
			$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s",
				sanitize_key( $status )
			);
		} else {
			$sql = "SELECT COUNT(*) FROM {$table}";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}
}

