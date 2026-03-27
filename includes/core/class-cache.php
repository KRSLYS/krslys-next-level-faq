<?php
/**
 * Simple caching utilities.
 *
 * @package Krslys\NextLevelFaq
 */

namespace Krslys\NextLevelFaq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles caching of rendered FAQ groups.
 */
class Cache {

	const GROUP = 'nlf_faq_cache';

	/**
	 * Retrieve cached HTML for a group.
	 *
	 * @param int   $group_id Group ID.
	 * @param array $context  Context array (attributes/settings).
	 *
	 * @return string|false
	 */
	public static function get_rendered_group( $group_id, $context ) {
		if ( $group_id <= 0 ) {
			return false;
		}

		$key    = self::build_key( $group_id, $context );
		$cached = wp_cache_get( $key, self::GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$transient = get_transient( $key );

		if ( false !== $transient ) {
			wp_cache_set( $key, $transient, self::GROUP, HOUR_IN_SECONDS );
		}

		return $transient;
	}

	/**
	 * Cache rendered group HTML.
	 *
	 * @param int    $group_id Group ID.
	 * @param array  $context  Context array.
	 * @param string $html     Rendered HTML.
	 *
	 * @return bool
	 */
	public static function set_rendered_group( $group_id, $context, $html ) {
		if ( $group_id <= 0 ) {
			return false;
		}

		$key = self::build_key( $group_id, $context );

		wp_cache_set( $key, $html, self::GROUP, 12 * HOUR_IN_SECONDS );
		set_transient( $key, $html, 12 * HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * Invalidate cache for a group by bumping its version.
	 *
	 * @param int $group_id Group ID.
	 *
	 * @return void
	 */
	public static function invalidate_group( $group_id ) {
		if ( $group_id <= 0 ) {
			return;
		}

		$version = self::get_version( $group_id );
		update_option( self::version_option_name( $group_id ), $version + 1, false );
	}

	/**
	 * Build cache key string.
	 *
	 * @param int   $group_id Group ID.
	 * @param array $context  Context data.
	 *
	 * @return string
	 */
	private static function build_key( $group_id, $context ) {
		$version = self::get_version( $group_id );
		$hash    = md5( wp_json_encode( $context ) );

		return sprintf( 'group_%d_v%s_%s', $group_id, $version, $hash );
	}

	/**
	 * Retrieve version token for group cache.
	 *
	 * @param int $group_id Group ID.
	 *
	 * @return int
	 */
	private static function get_version( $group_id ) {
		$version = get_option( self::version_option_name( $group_id ), 1 );

		if ( ! $version ) {
			$version = 1;
		}

		return (int) $version;
	}

	/**
	 * Build option name for per-group cache version.
	 *
	 * @param int $group_id Group ID.
	 *
	 * @return string
	 */
	private static function version_option_name( $group_id ) {
		return 'nlf_faq_cache_version_' . (int) $group_id;
	}
}

