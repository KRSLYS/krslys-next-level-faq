<?php
/**
 * Uninstall script for Next Level FAQ plugin.
 *
 * Runs when the plugin is deleted via WordPress admin.
 * Cleans up all plugin data from the database.
 *
 * @package Krslys\NextLevelFaq
 */

// Exit if not called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load the autoloader and classes
require_once plugin_dir_path( __FILE__ ) . 'includes/Autoloader.php';

$autoloader = new \Krslys\NextLevelFaq\Autoloader( plugin_dir_path( __FILE__ ) . 'includes' );
$autoloader->register();

// Import the Database class
use Krslys\NextLevelFaq\Database;
use Krslys\NextLevelFaq\Settings_Repository;
/**
 * Drop all custom tables.
 */
Database::drop_tables();

/**
 * Delete plugin options.
 */
delete_option( 'nlf_faq_schema_version' );
delete_option( 'nlf_faq_style_options' );
delete_option( 'nlf_faq_presets_css_version' );
/**
 * Delete generated CSS files from uploads directory using WP_Filesystem.
 */
$uploads = wp_upload_dir();
$css_dir = trailingslashit( $uploads['basedir'] ) . 'nlf-faq';

if ( is_dir( $css_dir ) ) {
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	WP_Filesystem();

	global $wp_filesystem;

	if ( $wp_filesystem ) {
		$wp_filesystem->rmdir( $css_dir, true );
	}
}

/**
 * Clear any transients.
 */
global $wpdb;

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_nlf_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_nlf_' ) . '%'
	)
);

