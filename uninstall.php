<?php
/**
 * Uninstall script for Next Level FAQ plugin.
 *
 * Runs when the plugin is deleted via WordPress admin.
 * Cleans up all plugin data from the database.
 *
 * @package Krslys\NextLevelFaqAccordion
 */

// Exit if not called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load the autoloader and classes
require_once plugin_dir_path( __FILE__ ) . 'includes/Autoloader.php';

$krslys_nlfa_autoloader = new \Krslys\NextLevelFaqAccordion\Autoloader( plugin_dir_path( __FILE__ ) . 'includes' );
$krslys_nlfa_autoloader->register();

// Import the Database class
use Krslys\NextLevelFaqAccordion\Database;
use Krslys\NextLevelFaqAccordion\Settings_Repository;
/**
 * Drop all custom tables.
 */
Database::drop_tables();

/**
 * Delete plugin options.
 */
delete_option( 'krslys_nlfa_schema_version' );
delete_option( 'nlf_faq_style_options' );
delete_option( 'nlf_faq_presets_css_version' );
delete_option( 'nlf_faq_css_version' );

/**
 * Remove custom capability from all roles.
 */
foreach ( wp_roles()->roles as $role_name => $role_info ) {
	$role = get_role( $role_name );
	if ( $role && $role->has_cap( 'manage_krslys_nlfa' ) ) {
		$role->remove_cap( 'manage_krslys_nlfa' );
	}
}
/**
 * Delete generated CSS files from uploads directory using WP_Filesystem.
 */
$krslys_nlfa_uploads = wp_upload_dir();
$krslys_nlfa_css_dir = trailingslashit( $krslys_nlfa_uploads['basedir'] ) . 'nlf-faq';

if ( is_dir( $krslys_nlfa_css_dir ) ) {
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	WP_Filesystem();

	global $wp_filesystem;

	if ( $wp_filesystem ) {
		$wp_filesystem->rmdir( $krslys_nlfa_css_dir, true );
	}
}

/**
 * Clear any transients.
 */
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup during uninstall, no caching needed.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_nlf_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_nlf_' ) . '%'
	)
);

