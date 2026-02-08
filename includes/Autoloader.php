<?php
/**
 * PSR-4 Autoloader for Krslys\NextLevelFaq namespace.
 *
 * @package Krslys\NextLevelFaq
 */

namespace Krslys\NextLevelFaq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PSR-4 Autoloader class.
 */
class Autoloader {

	/**
	 * Namespace prefix.
	 *
	 * @var string
	 */
	private $namespace_prefix = 'Krslys\\NextLevelFaq\\';

	/**
	 * Base directory for the namespace prefix.
	 *
	 * @var string
	 */
	private $base_dir;

	/**
	 * Constructor.
	 *
	 * @param string $base_dir Base directory for the namespace.
	 */
	public function __construct( $base_dir ) {
		$this->base_dir = rtrim( $base_dir, '/\\' ) . '/';
	}

	/**
	 * Register the autoloader.
	 */
	public function register() {
		spl_autoload_register( array( $this, 'load_class' ) );
	}

	/**
	 * Load a class file based on PSR-4 standard.
	 *
	 * @param string $class The fully-qualified class name.
	 *
	 * @return void
	 */
	public function load_class( $class ) {
		// Check if the class uses the namespace prefix.
		$prefix_length = strlen( $this->namespace_prefix );
		if ( strncmp( $this->namespace_prefix, $class, $prefix_length ) !== 0 ) {
			// No, move to the next registered autoloader.
			return;
		}

		// Get the relative class name.
		$relative_class = substr( $class, $prefix_length );

		// Convert class name to file name (WordPress style).
		$file_name = $this->get_file_name_from_class( $relative_class );

		// Build the file path.
		$file = $this->base_dir . $file_name;

		// If the file exists, require it.
		if ( file_exists( $file ) ) {
			require $file;
		}
	}

	/**
	 * Convert class name to file name following WordPress conventions.
	 *
	 * Maps class names to subdirectories and files based on naming patterns:
	 * - Admin_* classes -> admin/class-*.php
	 * - Frontend_* classes -> frontend/class-*.php
	 * - Core classes (Options, Repository, Style_Generator) -> core/class-*.php
	 * - *_CPT classes -> admin/class-*.php
	 *
	 * Examples:
	 *   Options -> core/class-options.php
	 *   Style_Generator -> core/class-style-generator.php
	 *   Admin_Settings -> admin/class-admin-settings.php
	 *   Group_CPT -> admin/class-group-cpt.php
	 *   Frontend_Renderer -> frontend/class-frontend-renderer.php
	 *
	 * @param string $class_name The class name.
	 *
	 * @return string The file path relative to base directory.
	 */
	private function get_file_name_from_class( $class_name ) {
		// Convert underscores to hyphens and lowercase.
		$file_name = str_replace( '_', '-', $class_name );
		$file_name = strtolower( $file_name );
		
		// Add class prefix and extension.
		$file_name = 'class-' . $file_name . '.php';
		
		// Determine subdirectory based on class name pattern.
		$subdirectory = $this->get_subdirectory_for_class( $class_name );
		
		if ( $subdirectory ) {
			return $subdirectory . '/' . $file_name;
		}
		
		return $file_name;
	}
	
	/**
	 * Determine the subdirectory for a class based on naming patterns.
	 *
	 * @param string $class_name The class name.
	 *
	 * @return string The subdirectory name (without trailing slash) or empty string.
	 */
	private function get_subdirectory_for_class( $class_name ) {
		// Admin classes: Admin_* or *_CPT.
		if ( strpos( $class_name, 'Admin_' ) === 0 || strpos( $class_name, '_CPT' ) !== false ) {
			return 'admin';
		}

		// Frontend classes: Frontend_*.
		if ( strpos( $class_name, 'Frontend_' ) === 0 ) {
			return 'frontend';
		}

		// Fallback: check if the file exists in core/ directory.
		$file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
		if ( file_exists( $this->base_dir . 'core/' . $file_name ) ) {
			return 'core';
		}

		// Default: no subdirectory (for backwards compatibility).
		return '';
	}
}

