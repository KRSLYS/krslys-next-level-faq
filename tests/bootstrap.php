<?php
/**
 * PHPUnit bootstrap for Next Level FAQ & Accordion plugin.
 *
 * Defines the minimum WordPress constants and stubs needed so plugin class
 * files can be loaded without a full WordPress installation.
 */

// ---------------------------------------------------------------------------
// Plugin constants
// ---------------------------------------------------------------------------
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'KRSLYS_NLFA_VERSION' ) ) {
	define( 'KRSLYS_NLFA_VERSION', '1.0.0' );
}
if ( ! defined( 'KRSLYS_NLFA_CSS_VERSION' ) ) {
	define( 'KRSLYS_NLFA_CSS_VERSION', '1.0.0' );
}
if ( ! defined( 'KRSLYS_NLFA_PLUGIN_DIR' ) ) {
	define( 'KRSLYS_NLFA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'KRSLYS_NLFA_PLUGIN_URL' ) ) {
	define( 'KRSLYS_NLFA_PLUGIN_URL', 'http://example.com/wp-content/plugins/krslys-next-level-faq-accordion/' );
}

// ---------------------------------------------------------------------------
// WordPress constants used by plugin classes
// ---------------------------------------------------------------------------
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'SCRIPT_DEBUG' ) ) {
	define( 'SCRIPT_DEBUG', false );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

// ---------------------------------------------------------------------------
// Composer autoloader (Brain Monkey, PHPUnit, test classes)
// ---------------------------------------------------------------------------
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// ---------------------------------------------------------------------------
// Plugin's own PSR-4-style autoloader
// ---------------------------------------------------------------------------
require_once dirname( __DIR__ ) . '/includes/Autoloader.php';

$nlf_autoloader = new \Krslys\NextLevelFaqAccordion\Autoloader( dirname( __DIR__ ) . '/includes' );
$nlf_autoloader->register();

// ---------------------------------------------------------------------------
// Global helper functions
// ---------------------------------------------------------------------------
require_once dirname( __DIR__ ) . '/includes/core/functions.php';
