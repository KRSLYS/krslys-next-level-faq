<?php
/**
 * PHPUnit bootstrap for Next Level FAQ plugin.
 *
 * Defines the minimum WordPress constants and stubs needed so plugin class
 * files can be loaded without a full WordPress installation.
 */

// ---------------------------------------------------------------------------
// Plugin constants
// ---------------------------------------------------------------------------
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'NLF_FAQ_VERSION', '1.0.0' );
define( 'NLF_FAQ_CSS_VERSION', '1.0.0' );
define( 'NLF_FAQ_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'NLF_FAQ_PLUGIN_URL', 'http://example.com/wp-content/plugins/krslys-next-level-faq-accordion/' );

// ---------------------------------------------------------------------------
// WordPress constants used by plugin classes
// ---------------------------------------------------------------------------
define( 'HOUR_IN_SECONDS', 3600 );
define( 'SCRIPT_DEBUG', false );
define( 'OBJECT', 'OBJECT' );
define( 'ARRAY_A', 'ARRAY_A' );

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
// Global helper functions (nlf_asset_url / nlf_asset_path)
// ---------------------------------------------------------------------------
require_once dirname( __DIR__ ) . '/includes/core/functions.php';
