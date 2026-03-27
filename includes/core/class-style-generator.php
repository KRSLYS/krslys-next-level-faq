<?php
/**
 * Builds and stores generated CSS for FAQ styles.
 *
 * @package Krslys\NextLevelFaq
 */

namespace Krslys\NextLevelFaq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and stores generated CSS for FAQ styles.
 *
 * SECURITY FEATURES:
 * - Uses WordPress Filesystem API for all file operations.
 * - Validates directory paths and file permissions.
 * - Sanitizes all CSS values via esc_html().
 */
class Style_Generator {

	/**
	 * Get path to generated CSS file.
	 *
	 * @return string
	 */
	public static function get_css_file_path() {
		$upload_dir = wp_upload_dir();
		$dir        = trailingslashit( $upload_dir['basedir'] ) . 'nlf-faq';

		wp_mkdir_p( $dir );

		return trailingslashit( $dir ) . 'generated-faq-style.css';
	}

	/**
	 * Get URL to generated CSS file.
	 *
	 * @return string|false
	 */
	public static function get_css_file_url() {
		$upload_dir = wp_upload_dir();
		$url        = trailingslashit( $upload_dir['baseurl'] ) . 'nlf-faq/generated-faq-style.css';

		return $url;
	}

	/**
	 * Build CSS string from options.
	 *
	 * SECURITY: All option values escaped with esc_html() before output.
	 *
	 * @param array $options Options (resolved with presets recommended).
	 *
	 * @return string
	 */
	public static function build_css( $options ) {
		$normalized = self::normalize_options( $options );
		$o          = $normalized['options'];
		$c          = $normalized['computed'];

		ob_start();
		?>
/* =======================================================
   Next Level FAQ — Premium Design System v2
   Professional-grade accordion styling
   ======================================================= */

:root {
	/* ---- Typography ---- */
	--nlf-faq-font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;

	/* ---- Color Tokens (user-defined) ---- */
	--nlf-faq-container-bg: <?php echo esc_html( $o['container_background'] ); ?>;
	--nlf-faq-border-color: <?php echo esc_html( $o['container_border_color'] ); ?>;
	--nlf-faq-question-color: <?php echo esc_html( $o['question_color'] ); ?>;
	--nlf-faq-answer-color: <?php echo esc_html( $o['answer_color'] ); ?>;
	--nlf-faq-accent-color: <?php echo esc_html( $o['accent_color'] ); ?>;

	/* ---- Spacing ---- */
	--nlf-faq-border-radius: <?php echo esc_html( $c['border_radius_rem'] ); ?>rem;
	--nlf-faq-padding: <?php echo esc_html( $c['padding_rem'] ); ?>rem;
	--nlf-faq-gap: <?php echo esc_html( $c['gap_rem'] ); ?>rem;

	/* ---- Typography Sizes ---- */
	--nlf-faq-question-size: <?php echo esc_html( $c['question_font_rem'] ); ?>rem;
	--nlf-faq-answer-size: <?php echo esc_html( $c['answer_font_rem'] ); ?>rem;
	--nlf-faq-question-weight: <?php echo intval( $o['question_font_weight'] ); ?>;

	/* ---- Elevation ---- */
	--nlf-faq-shadow: <?php echo esc_html( $c['shadow_css'] ); ?>;

	/* ---- Motion ---- */
	--nlf-faq-transition: <?php echo esc_html( $c['transition_base'] ); ?>;
	--nlf-faq-answer-transition: <?php echo esc_html( $c['answer_transition'] ); ?>;
}


/* =======================================================
   Base Container
   ======================================================= */

.nlf-faq {
	font-family: var(--nlf-faq-font-family);
	background: var(--nlf-faq-container-bg);
	border: 1px solid var(--nlf-faq-border-color);
	border-radius: var(--nlf-faq-border-radius);
	padding: var(--nlf-faq-padding);
	box-shadow: var(--nlf-faq-shadow);
	box-sizing: border-box;
	width: 100%;
	max-width: 100%;
	-webkit-font-smoothing: antialiased;
	-moz-osx-font-smoothing: grayscale;
}

.nlf-faq *,
.nlf-faq *::before,
.nlf-faq *::after {
	box-sizing: border-box;
}


/* =======================================================
   Search
   ======================================================= */

.nlf-faq-search {
	margin-bottom: 1.25rem;
}

.nlf-faq-search-input {
	display: block;
	width: 100%;
	padding: 0.8125rem 1rem 0.8125rem 2.75rem;
	font-size: 0.9375rem;
	font-family: var(--nlf-faq-font-family);
	color: var(--nlf-faq-question-color);
	background-color: var(--nlf-faq-container-bg);
	background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='none' stroke='%239ca3af' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' viewBox='0 0 24 24'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E");
	background-repeat: no-repeat;
	background-position: 0.875rem center;
	background-size: 1.125rem;
	border: 1.5px solid var(--nlf-faq-border-color);
	border-radius: var(--nlf-faq-border-radius);
	transition: border-color 180ms ease, box-shadow 180ms ease;
	box-sizing: border-box;
}

.nlf-faq-search-input::placeholder {
	color: var(--nlf-faq-answer-color);
	opacity: 0.6;
}

.nlf-faq-search-input:focus {
	outline: none;
	border-color: var(--nlf-faq-accent-color);
	box-shadow: 0 0 0 3px rgba(59,130,246,.12);
}


/* =======================================================
   Counter Badge
   ======================================================= */

.nlf-faq__counter {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 1.625rem;
	height: 1.625rem;
	padding: 0 0.375rem;
	margin-right: 0.75rem;
	font-size: 0.75rem;
	font-weight: 700;
	line-height: 1;
	letter-spacing: 0.02em;
	color: var(--nlf-faq-accent-color);
	background: rgba(59,130,246,.08);
	border-radius: 9999px;
	flex-shrink: 0;
}


/* =======================================================
   FAQ Item  —  Flat (default)
   ======================================================= */

.nlf-faq__item {
	border-bottom: 1px solid var(--nlf-faq-border-color);
	padding: 0;
	transition: background 200ms ease;
}

.nlf-faq__item:last-child {
	border-bottom: none;
}

.nlf-faq__item + .nlf-faq__item {
	margin-top: var(--nlf-faq-gap);
}


/* =======================================================
   Question
   ======================================================= */

.nlf-faq__question {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 1rem;
	width: 100%;
	padding: 1.125rem 0.25rem;
	margin: 0;
	border: none;
	background: none;
	cursor: pointer;
	text-align: left;
	color: var(--nlf-faq-question-color);
	font-family: var(--nlf-faq-font-family);
	font-size: var(--nlf-faq-question-size);
	font-weight: var(--nlf-faq-question-weight);
	line-height: 1.4;
	letter-spacing: -0.015em;
	-webkit-tap-highlight-color: transparent;
	transition: color 180ms ease;
}

.nlf-faq__question:hover {
	color: var(--nlf-faq-accent-color);
}

.nlf-faq__question:focus {
	outline: none;
}

.nlf-faq__question:focus-visible {
	outline: 2px solid var(--nlf-faq-accent-color);
	outline-offset: 3px;
	border-radius: 4px;
}


/* =======================================================
   Icon — Shared
   ======================================================= */

.nlf-faq__icon {
	position: relative;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 2rem;
	height: 2rem;
	min-width: 2rem;
	border-radius: 50%;
	background: rgba(59,130,246,.06);
	color: var(--nlf-faq-accent-color);
	flex-shrink: 0;
	transition: transform 280ms cubic-bezier(.4,0,.2,1),
	            background 200ms ease,
	            color 200ms ease;
}

.nlf-faq__question:hover .nlf-faq__icon {
	background: rgba(59,130,246,.12);
}

.nlf-faq__item.is-open .nlf-faq__icon {
	background: var(--nlf-faq-accent-color);
	color: #fff;
}

/* Plus / Minus (default) */
.nlf-faq__icon::before,
.nlf-faq__icon::after {
	content: '';
	position: absolute;
	background: currentColor;
	border-radius: 1px;
	transition: transform 280ms cubic-bezier(.4,0,.2,1),
	            opacity 200ms ease;
}

/* Horizontal bar */
.nlf-faq__icon::before {
	width: 0.75rem;
	height: 2px;
}

/* Vertical bar (forms the +) */
.nlf-faq__icon::after {
	width: 2px;
	height: 0.75rem;
}

/* When open: vertical bar rotates to 0 (becomes –) */
.nlf-faq__item.is-open .nlf-faq__icon::after {
	transform: rotate(90deg);
	opacity: 0;
}

/* ----- Chevron ----- */

.nlf-faq--icon-chevron .nlf-faq__icon::before {
	width: 0.5rem;
	height: 0.5rem;
	background: none;
	border-right: 2px solid currentColor;
	border-bottom: 2px solid currentColor;
	border-radius: 0;
	transform: rotate(45deg);
	margin-top: -3px;
	transition: transform 280ms cubic-bezier(.4,0,.2,1);
}

.nlf-faq--icon-chevron .nlf-faq__icon::after {
	display: none;
}

.nlf-faq--icon-chevron .nlf-faq__item.is-open .nlf-faq__icon::before {
	transform: rotate(-135deg);
	margin-top: 3px;
	opacity: 1;
}

/* ----- Arrow ----- */

.nlf-faq--icon-arrow .nlf-faq__icon::before {
	width: 0.625rem;
	height: 2px;
	background: currentColor;
	border: none;
	border-radius: 1px;
	transform: none;
	margin: 0;
}

.nlf-faq--icon-arrow .nlf-faq__icon::after {
	width: 0;
	height: 0;
	background: none;
	border-left: 5px solid currentColor;
	border-top: 4px solid transparent;
	border-bottom: 4px solid transparent;
	border-radius: 0;
	position: absolute;
	right: calc(50% - 6px);
	transform: none;
	opacity: 1;
	transition: transform 280ms cubic-bezier(.4,0,.2,1);
}

.nlf-faq--icon-arrow .nlf-faq__item.is-open .nlf-faq__icon {
	transform: rotate(90deg);
}

.nlf-faq--icon-arrow .nlf-faq__item.is-open .nlf-faq__icon::after {
	opacity: 1;
}


/* =======================================================
   Answer
   ======================================================= */

.nlf-faq__answer {
	max-height: 0;
	overflow: hidden;
	opacity: 0;
	transform: translateY(-4px);
	color: var(--nlf-faq-answer-color);
	font-size: var(--nlf-faq-answer-size);
	line-height: 1.7;
	padding: 0 0.25rem;
	transition: var(--nlf-faq-answer-transition);
}

.nlf-faq__item.is-open .nlf-faq__answer {
	max-height: 5000px;
	opacity: 1;
	transform: translateY(0);
	padding-bottom: 1.125rem;
}

.nlf-faq__answer p {
	margin: 0 0 0.875rem 0;
}

.nlf-faq__answer p:last-child {
	margin-bottom: 0;
}

.nlf-faq__answer ul,
.nlf-faq__answer ol {
	margin: 0.5rem 0 1rem 1.25rem;
	padding: 0;
}

.nlf-faq__answer li {
	margin-bottom: 0.375rem;
}

.nlf-faq__answer li:last-child {
	margin-bottom: 0;
}

.nlf-faq__answer a {
	color: var(--nlf-faq-accent-color);
	text-decoration: underline;
	text-underline-offset: 2px;
	transition: opacity 150ms ease;
}

.nlf-faq__answer a:hover {
	opacity: 0.8;
}

.nlf-faq__answer h1,
.nlf-faq__answer h2,
.nlf-faq__answer h3,
.nlf-faq__answer h4,
.nlf-faq__answer h5,
.nlf-faq__answer h6 {
	color: var(--nlf-faq-question-color);
	margin: 1.25rem 0 0.5rem;
	line-height: 1.3;
}

.nlf-faq__answer h1:first-child,
.nlf-faq__answer h2:first-child,
.nlf-faq__answer h3:first-child {
	margin-top: 0;
}

.nlf-faq__answer img {
	max-width: 100%;
	height: auto;
	border-radius: calc(var(--nlf-faq-border-radius) * 0.5);
}

.nlf-faq__answer blockquote {
	margin: 0.75rem 0;
	padding: 0.75rem 1rem;
	border-left: 3px solid var(--nlf-faq-accent-color);
	background: rgba(0,0,0,.02);
	border-radius: 0 6px 6px 0;
	font-style: italic;
}

.nlf-faq__answer table {
	width: 100%;
	border-collapse: collapse;
	margin: 0.75rem 0;
	font-size: 0.875em;
}

.nlf-faq__answer th,
.nlf-faq__answer td {
	padding: 0.5rem 0.75rem;
	border: 1px solid var(--nlf-faq-border-color);
	text-align: left;
}

.nlf-faq__answer th {
	background: rgba(0,0,0,.03);
	font-weight: 600;
}


/* =======================================================
   Highlight (search match)
   ======================================================= */

.nlf-faq__item--highlight {
	background: rgba(59,130,246,.04);
	border-radius: var(--nlf-faq-border-radius);
	padding-left: 0.75rem;
	padding-right: 0.75rem;
}


/* =======================================================
   LAYOUT  —  Cards
   Floating individual cards per item, no outer container
   ======================================================= */

.nlf-faq--layout-cards {
	background: transparent;
	border: none;
	box-shadow: none;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: var(--nlf-faq-gap);
}

.nlf-faq--layout-cards .nlf-faq__item {
	background: var(--nlf-faq-container-bg);
	border: 1px solid var(--nlf-faq-border-color);
	border-radius: var(--nlf-faq-border-radius);
	padding: 0.25rem 1.25rem;
	box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.06);
	transition: box-shadow 200ms ease, border-color 200ms ease;
}

.nlf-faq--layout-cards .nlf-faq__item + .nlf-faq__item {
	margin-top: 0;
}

.nlf-faq--layout-cards .nlf-faq__item:last-child {
	border-bottom: 1px solid var(--nlf-faq-border-color);
}

.nlf-faq--layout-cards .nlf-faq__item:hover {
	box-shadow: 0 4px 12px rgba(0,0,0,.06), 0 2px 4px rgba(0,0,0,.04);
}

.nlf-faq--layout-cards .nlf-faq__item.is-open {
	border-color: var(--nlf-faq-accent-color);
	box-shadow: 0 4px 16px rgba(0,0,0,.08), 0 2px 6px rgba(0,0,0,.04);
}

.nlf-faq--layout-cards .nlf-faq__item.is-open .nlf-faq__answer {
	padding-bottom: 1.25rem;
}

.nlf-faq--layout-cards .nlf-faq-search {
	padding: 0;
	margin-bottom: var(--nlf-faq-gap);
}


/* =======================================================
   LAYOUT  —  Bordered
   Stacked accordion with connected borders
   ======================================================= */

.nlf-faq--layout-bordered .nlf-faq__item {
	border: 1px solid var(--nlf-faq-border-color);
	border-bottom: none;
	border-radius: 0;
	padding: 0 1.25rem;
	margin-top: 0;
	transition: background 200ms ease, border-color 200ms ease;
}

.nlf-faq--layout-bordered .nlf-faq__item + .nlf-faq__item {
	margin-top: 0;
}

.nlf-faq--layout-bordered .nlf-faq__item:first-child {
	border-top-left-radius: var(--nlf-faq-border-radius);
	border-top-right-radius: var(--nlf-faq-border-radius);
}

.nlf-faq--layout-bordered .nlf-faq__item:last-child {
	border-bottom: 1px solid var(--nlf-faq-border-color);
	border-bottom-left-radius: var(--nlf-faq-border-radius);
	border-bottom-right-radius: var(--nlf-faq-border-radius);
}

.nlf-faq--layout-bordered .nlf-faq__item.is-open {
	background: rgba(0,0,0,.015);
}


/* =======================================================
   LAYOUT  —  Clean
   No borders, left accent bar on answers
   ======================================================= */

.nlf-faq--layout-clean {
	border: none;
	box-shadow: none;
}

.nlf-faq--layout-clean .nlf-faq__item {
	border: none;
	padding: 0;
}

.nlf-faq--layout-clean .nlf-faq__item:last-child {
	border-bottom: none;
}

.nlf-faq--layout-clean .nlf-faq__item + .nlf-faq__item {
	margin-top: 0;
}

.nlf-faq--layout-clean .nlf-faq__question {
	padding: 0.875rem 0;
}

.nlf-faq--layout-clean .nlf-faq__item.is-open .nlf-faq__answer {
	padding-left: 1rem;
	margin-left: 0.125rem;
	border-left: 3px solid var(--nlf-faq-accent-color);
}


/* =======================================================
   LAYOUT  —  Striped
   Alternating tinted rows
   ======================================================= */

.nlf-faq--layout-striped .nlf-faq__item {
	border-bottom: none;
	border-radius: 0;
	padding: 0 1.25rem;
}

.nlf-faq--layout-striped .nlf-faq__item:last-child {
	border-bottom: none;
}

.nlf-faq--layout-striped .nlf-faq__item + .nlf-faq__item {
	margin-top: 0;
}

.nlf-faq--layout-striped .nlf-faq__item:nth-child(odd) {
	background: rgba(0,0,0,.022);
}

.nlf-faq--layout-striped .nlf-faq__item:first-child {
	border-top-left-radius: calc(var(--nlf-faq-border-radius) * 0.5);
	border-top-right-radius: calc(var(--nlf-faq-border-radius) * 0.5);
}

.nlf-faq--layout-striped .nlf-faq__item:last-child {
	border-bottom-left-radius: calc(var(--nlf-faq-border-radius) * 0.5);
	border-bottom-right-radius: calc(var(--nlf-faq-border-radius) * 0.5);
}

.nlf-faq--layout-striped .nlf-faq__item.is-open {
	background: rgba(59,130,246,.04);
}


/* =======================================================
   Responsive
   ======================================================= */

@media (min-width: 48rem) {
	.nlf-faq:not(.nlf-faq--layout-cards) {
		padding: calc(var(--nlf-faq-padding) * 1.2);
	}
}

@media (max-width: 47.9375rem) {
	.nlf-faq:not(.nlf-faq--layout-cards) {
		padding: calc(var(--nlf-faq-padding) * 0.8);
		border-radius: calc(var(--nlf-faq-border-radius) * 0.75);
	}

	.nlf-faq__question {
		font-size: calc(var(--nlf-faq-question-size) * 0.94);
		padding-top: 0.875rem;
		padding-bottom: 0.875rem;
	}

	.nlf-faq__answer {
		font-size: calc(var(--nlf-faq-answer-size) * 0.96);
	}

	.nlf-faq--layout-cards .nlf-faq__item {
		padding: 0.125rem 1rem;
	}

	.nlf-faq--layout-bordered .nlf-faq__item,
	.nlf-faq--layout-striped .nlf-faq__item {
		padding-left: 1rem;
		padding-right: 1rem;
	}
}


/* =======================================================
   Print
   ======================================================= */

@media print {
	.nlf-faq {
		box-shadow: none !important;
		border: 1px solid #d1d5db !important;
	}

	.nlf-faq__answer {
		max-height: none !important;
		opacity: 1 !important;
		transform: none !important;
		overflow: visible !important;
		padding-bottom: 0.75rem !important;
	}

	.nlf-faq__icon,
	.nlf-faq-search {
		display: none !important;
	}
}
		<?php

		$css = ob_get_clean();

		return trim( $css );
	}

	/**
	 * Initialize WordPress Filesystem API.
	 *
	 * SECURITY: Uses WP_Filesystem for secure file operations.
	 *
	 * @return \WP_Filesystem_Base|false Filesystem instance or false on failure.
	 */
	private static function init_filesystem() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Initialize filesystem with direct method (safe for uploads directory).
		$credentials = request_filesystem_credentials( '', '', false, false, null );

		if ( false === $credentials ) {
			// Fallback to direct method for uploads directory.
			if ( ! WP_Filesystem() ) {
				return false;
			}
		} elseif ( ! WP_Filesystem( $credentials ) ) {
			return false;
		}

		global $wp_filesystem;

		return $wp_filesystem ? $wp_filesystem : false;
	}

	/**
	 * Get WordPress filesystem path, handling FTP_BASE if defined.
	 *
	 * @param string $path Local file path.
	 * @return string Filesystem-compatible path.
	 */
	private static function get_filesystem_path( $path ) {
		if ( defined( 'FTP_BASE' ) ) {
			return str_replace( ABSPATH, trailingslashit( FTP_BASE ), $path );
		}

		return $path;
	}

	/**
	 * Generate CSS file from current options using WordPress Filesystem API.
	 *
	 * SECURITY:
	 * - Uses WP_Filesystem for secure file operations.
	 * - Validates file paths and permissions.
	 * - Creates directory with proper permissions if needed.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function generate_and_save() {
		$options = Options::get_resolved_options();
		$css     = self::build_css( $options );
		$path    = self::get_css_file_path();

		if ( ! $path ) {
			return false;
		}

		$wp_filesystem = self::init_filesystem();

		if ( ! $wp_filesystem ) {
			return false;
		}

		$dir = dirname( $path );

		if ( ! $wp_filesystem->is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return false;
			}
		}

		$wp_path = self::get_filesystem_path( $path );

		$result = $wp_filesystem->put_contents(
			$wp_path,
			$css,
			FS_CHMOD_FILE
		);

		return false !== $result;
	}

	/**
	 * Generate CSS for all presets (used on activation).
	 * 
	 * This generates the main global CSS file.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function generate_all_presets() {
		return self::generate_and_save();
	}

	/**
	 * Get path to group-specific CSS file.
	 *
	 * @param int $group_id Group ID.
	 * @return string
	 */
	public static function get_group_css_file_path( $group_id ) {
		$upload_dir = wp_upload_dir();
		$dir        = trailingslashit( $upload_dir['basedir'] ) . 'nlf-faq/groups';

		wp_mkdir_p( $dir );

		return trailingslashit( $dir ) . 'group-' . absint( $group_id ) . '.css';
	}

	/**
	 * Get URL to group-specific CSS file.
	 *
	 * @param int $group_id Group ID.
	 * @return string|false
	 */
	public static function get_group_css_file_url( $group_id ) {
		$upload_dir = wp_upload_dir();
		$url        = trailingslashit( $upload_dir['baseurl'] ) . 'nlf-faq/groups/group-' . absint( $group_id ) . '.css';

		return $url;
	}

	/**
	 * Generate and save CSS for a specific group.
	 *
	 * SECURITY:
	 * - Uses WP_Filesystem for secure file operations.
	 * - Validates file paths and permissions.
	 *
	 * @param int   $group_id Group ID.
	 * @param array $options  Style options for the group.
	 * @return bool True on success, false on failure.
	 */
	public static function generate_and_save_for_group( $group_id, $options ) {
		$group_id = absint( $group_id );

		if ( ! $group_id ) {
			return false;
		}

		$resolved_options = Options::resolve_for_preset( $options['preset'] ?? null, $options );
		$css  = self::build_css( $resolved_options );
		$path = self::get_group_css_file_path( $group_id );

		if ( ! $path ) {
			return false;
		}

		$wp_filesystem = self::init_filesystem();

		if ( ! $wp_filesystem ) {
			return false;
		}

		$dir = dirname( $path );

		if ( ! $wp_filesystem->is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return false;
			}
		}

		$wp_path = self::get_filesystem_path( $path );

		$result = $wp_filesystem->put_contents(
			$wp_path,
			$css,
			FS_CHMOD_FILE
		);

		if ( false !== $result ) {
			Cache::invalidate_group( $group_id );
			return true;
		}

		return false;
	}

	/**
	 * Delete group-specific CSS file.
	 *
	 * SECURITY: Uses WP_Filesystem for secure file operations.
	 *
	 * @param int $group_id Group ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_group_css( $group_id ) {
		$group_id = absint( $group_id );

		if ( ! $group_id ) {
			return false;
		}

		$path = self::get_group_css_file_path( $group_id );

		if ( ! file_exists( $path ) ) {
			return true; // Already deleted or never existed.
		}

		$wp_filesystem = self::init_filesystem();

		if ( ! $wp_filesystem ) {
			return false;
		}

		$wp_path = self::get_filesystem_path( $path );

		$deleted = $wp_filesystem->delete( $wp_path );

		if ( $deleted ) {
			Cache::invalidate_group( $group_id );
		}

		return $deleted;
	}

	/**
	 * Build inline CSS variable declarations for a rendered FAQ wrapper.
	 *
	 * @param array $options Options (resolved).
	 *
	 * @return string
	 */
	public static function build_inline_style( $options ) {
		$normalized = self::normalize_options( $options );
		$o          = $normalized['options'];
		$c          = $normalized['computed'];

		$props = array(
			'--nlf-faq-container-bg'      => $o['container_background'],
			'--nlf-faq-border-color'      => $o['container_border_color'],
			'--nlf-faq-question-color'    => $o['question_color'],
			'--nlf-faq-answer-color'      => $o['answer_color'],
			'--nlf-faq-accent-color'      => $o['accent_color'],
			'--nlf-faq-border-radius'     => $c['border_radius_rem'] . 'rem',
			'--nlf-faq-padding'           => $c['padding_rem'] . 'rem',
			'--nlf-faq-gap'               => $c['gap_rem'] . 'rem',
			'--nlf-faq-question-size'     => $c['question_font_rem'] . 'rem',
			'--nlf-faq-answer-size'       => $c['answer_font_rem'] . 'rem',
			'--nlf-faq-question-weight'   => (string) intval( $o['question_font_weight'] ),
			'--nlf-faq-shadow'            => $c['shadow_css'],
			'--nlf-faq-transition'        => $c['transition_base'],
			'--nlf-faq-answer-transition' => $c['answer_transition'],
		);

		$parts = array();

		foreach ( $props as $name => $value ) {
			if ( '' === $value && '0' !== $value ) {
				continue;
			}
			$parts[] = $name . ':' . $value;
		}

		return implode( ';', $parts );
	}

	/**
	 * Normalize options and compute derivative values for CSS.
	 *
	 * @param array $options Raw/resolved options.
	 *
	 * @return array
	 */
	private static function normalize_options( $options ) {
		$o = Options::resolve_for_preset( $options['preset'] ?? null, $options );

		$border_radius_rem = round( intval( $o['container_border_radius'] ) / 16, 3 );
		$padding_rem       = round( intval( $o['container_padding'] ) / 16, 3 );
		$question_font_rem = round( intval( $o['question_font_size'] ) / 16, 3 );
		$answer_font_rem   = round( intval( $o['answer_font_size'] ) / 16, 3 );
		$gap_rem           = round( intval( $o['gap_between_items'] ) / 16, 3 );

		$shadow_style = $o['shadow'] ?? false;

		if ( is_string( $shadow_style ) && 'none' !== $shadow_style && '' !== $shadow_style ) {
			$shadow_map = array(
				'sm'      => '0 1px 2px rgba(0,0,0,0.06), 0 1px 3px rgba(0,0,0,0.1)',
				'md'      => '0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1)',
				'lg'      => '0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1)',
				'xl'      => '0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1)',
				'colored' => '0 4px 14px -3px color-mix(in srgb, ' . esc_html( $o['accent_color'] ?? '#3b82f6' ) . ' 25%, transparent)',
			);
			$shadow_css = isset( $shadow_map[ $shadow_style ] ) ? $shadow_map[ $shadow_style ] : 'none';
		} elseif ( ! empty( $shadow_style ) && true === $shadow_style || '1' === $shadow_style || 1 === $shadow_style ) {
			$shadow_css = '0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1)';
		} else {
			$shadow_css = 'none';
		}

		$transition_base   = '200ms cubic-bezier(0.4, 0, 0.2, 1)';
		$answer_transition = 'max-height 280ms cubic-bezier(0.4, 0, 0.2, 1), opacity 220ms ease, transform 220ms ease';

		if ( 'fade' === ( $o['animation'] ?? 'slide' ) ) {
			$answer_transition = 'max-height 200ms ease, opacity 250ms ease, transform 180ms ease';
		} elseif ( 'none' === ( $o['animation'] ?? 'slide' ) ) {
			$answer_transition = 'none';
		}

		return array(
			'options'  => $o,
			'computed' => array(
				'border_radius_rem'  => $border_radius_rem,
				'padding_rem'        => $padding_rem,
				'question_font_rem'  => $question_font_rem,
				'answer_font_rem'    => $answer_font_rem,
				'gap_rem'            => $gap_rem,
				'shadow_css'         => $shadow_css,
				'transition_base'    => $transition_base,
				'answer_transition'  => $answer_transition,
			),
		);
	}
}
