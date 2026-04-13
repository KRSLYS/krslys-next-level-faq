<?php
/**
 * Uninstall script for Next Level FAQ & Accordion.
 *
 * Runs when the plugin is deleted via WordPress admin.
 * Cleans up all plugin data from the database.
 *
 * @package Krslys\NextLevelFaqAccordion
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load the autoloader and classes.
require_once plugin_dir_path( __FILE__ ) . 'includes/Autoloader.php';

$krslys_nlfa_autoloader = new \Krslys\NextLevelFaqAccordion\Autoloader( plugin_dir_path( __FILE__ ) . 'includes' );
$krslys_nlfa_autoloader->register();

use Krslys\NextLevelFaqAccordion\Database;

/**
 * Drop all custom tables (groups, items, settings).
 * This removes ALL plugin data including settings stored in krslys_nlfa_settings.
 */
Database::drop_tables();

/**
 * Delete schema version from wp_options (the only wp_options entry).
 */
delete_option( 'krslys_nlfa_schema_version' );

/**
 * Remove custom capability from all roles.
 */
foreach ( wp_roles()->roles as $krslys_nlfa_role_name => $krslys_nlfa_role_info ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Foreach requires both key and value.
	$krslys_nlfa_role = get_role( $krslys_nlfa_role_name );
	if ( $krslys_nlfa_role && $krslys_nlfa_role->has_cap( 'manage_krslys_nlfa' ) ) {
		$krslys_nlfa_role->remove_cap( 'manage_krslys_nlfa' );
	}
}

/**
 * Delete generated CSS files from uploads directory.
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
