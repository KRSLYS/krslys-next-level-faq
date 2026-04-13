<?php
/**
 * Admin settings page and assets.
 *
 * @package Krslys\NextLevelFaqAccordion
 */

namespace Krslys\NextLevelFaqAccordion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings page and assets.
 *
 * SECURITY FEATURES:
 * - All admin actions require 'manage_krslys_nlfa' or 'manage_options' capability.
 * - All forms protected with nonce verification.
 * - File uploads thoroughly validated (MIME type, size, extension).
 * - All inputs sanitized, all outputs escaped.
 * - Uses WordPress Filesystem API for file operations.
 */
class Admin_Settings {

	/**
	 * Custom capability for managing FAQ & Accordion content.
	 *
	 * Granted to administrators on activation. Site owners can assign
	 * this capability to other roles or individual users to delegate
	 * FAQ management without granting full admin access.
	 */
	const CAPABILITY = 'manage_krslys_nlfa';

	/**
	 * Top-level menu slug.
	 */
	const TOP_MENU_SLUG = 'nlf-faq';

	/**
	 * Questions page slug.
	 */
	const QUESTIONS_SLUG = 'nlf-faq-questions';

	/**
	 * Tools page slug.
	 */
	const TOOLS_SLUG = 'nlf-faq-tools';

	/**
	 * Check if the current user can manage FAQ & Accordion content.
	 *
	 * Returns true if the user has either the custom capability
	 * or full admin privileges.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		return current_user_can( self::CAPABILITY ) || current_user_can( 'manage_options' );
	}

	/**
	 * Bootstrap all admin-settings hooks.
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_nlf_faq_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_nlf_faq_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_nlf_faq_save_settings', array( __CLASS__, 'handle_save_settings' ) );
	}

	/**
	 * Register admin menu.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Next Level FAQ & Accordion', 'krslys-next-level-faq-accordion' ),
			__( 'FAQs', 'krslys-next-level-faq-accordion' ),
			self::CAPABILITY,
			self::TOP_MENU_SLUG,
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-editor-help',
			26
		);

		// First submenu uses the same slug as the parent to avoid a duplicate entry.
		add_submenu_page(
			self::TOP_MENU_SLUG,
			__( 'Dashboard', 'krslys-next-level-faq-accordion' ),
			__( 'Dashboard', 'krslys-next-level-faq-accordion' ),
			self::CAPABILITY,
			self::TOP_MENU_SLUG,
			array( __CLASS__, 'render_dashboard_page' )
		);

		add_submenu_page(
			self::TOP_MENU_SLUG,
			__( 'FAQ Groups', 'krslys-next-level-faq-accordion' ),
			__( 'FAQ Groups', 'krslys-next-level-faq-accordion' ),
			self::CAPABILITY,
			'nlf-faq-groups',
			array( __CLASS__, 'render_faq_groups_page' )
		);

		add_submenu_page(
			self::TOP_MENU_SLUG,
			__( 'Accordion Groups', 'krslys-next-level-faq-accordion' ),
			__( 'Accordion Groups', 'krslys-next-level-faq-accordion' ),
			self::CAPABILITY,
			'nlf-accordion-groups',
			array( __CLASS__, 'render_accordion_groups_page' )
		);

		add_submenu_page(
			self::TOP_MENU_SLUG,
			__( 'FAQ Tools', 'krslys-next-level-faq-accordion' ),
			__( 'Tools', 'krslys-next-level-faq-accordion' ),
			self::CAPABILITY,
			self::TOOLS_SLUG,
			array( __CLASS__, 'render_tools_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * SECURITY: Sanitizes $_GET['page'] before use.
	 *
	 * @param string $hook_suffix Current screen hook.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page routing for asset enqueueing only.
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page routing for asset enqueueing only.
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );

		$allowed_pages = array(
			self::TOP_MENU_SLUG,
			self::QUESTIONS_SLUG,
			self::TOOLS_SLUG,
			'nlf-faq-groups',
			'nlf-accordion-groups',
		);

		if ( ! in_array( $page, $allowed_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'nlf-faq-admin',
			krslys_nlfa_asset_url( 'assets/css/admin-faq-style.css' ),
			array(),
			NLF_FAQ_CSS_VERSION
		);
	}

	/**
	 * Render the plugin dashboard / welcome page.
	 *
	 * SECURITY: Capability check at start of function.
	 */
	public static function render_dashboard_page() {
		if ( ! self::current_user_can_manage() ) {
			return;
		}

		$faq_count       = \Krslys\NextLevelFaqAccordion\Groups_Repository::count_groups( null, 'faq' );
		$accordion_count = \Krslys\NextLevelFaqAccordion\Groups_Repository::count_groups( null, 'accordion' );
		$groups_url      = admin_url( 'admin.php?page=nlf-faq-groups' );
		$accordion_url   = admin_url( 'admin.php?page=nlf-accordion-groups' );
		$tools_url       = admin_url( 'admin.php?page=' . self::TOOLS_SLUG );
		?>
		<div class="wrap nlf-faq-admin nlf-faq-dashboard">

			<div class="nlf-dashboard-hero">
				<span class="dashicons dashicons-editor-help nlf-dashboard-hero__icon"></span>
				<h1 class="nlf-dashboard-hero__title"><?php esc_html_e( 'Next Level FAQ & Accordion', 'krslys-next-level-faq-accordion' ); ?></h1>
				<p class="nlf-dashboard-hero__desc"><?php esc_html_e( 'Flexible FAQ and Accordion plugin with customizable styling and live preview.', 'krslys-next-level-faq-accordion' ); ?></p>
			</div>

			<div class="nlf-dashboard-cards">

				<a href="<?php echo esc_url( $groups_url ); ?>" class="nlf-dashboard-card">
					<span class="dashicons dashicons-editor-help nlf-dashboard-card__icon"></span>
					<h2 class="nlf-dashboard-card__title"><?php esc_html_e( 'FAQ Groups', 'krslys-next-level-faq-accordion' ); ?></h2>
					<p class="nlf-dashboard-card__meta">
						<?php
						printf(
							/* translators: %d: number of FAQ groups */
							esc_html( _n( '%d group', '%d groups', $faq_count, 'krslys-next-level-faq-accordion' ) ),
							(int) $faq_count
						);
						?>
					</p>
					<p class="nlf-dashboard-card__desc"><?php esc_html_e( 'Create and manage your FAQ groups and questions.', 'krslys-next-level-faq-accordion' ); ?></p>
				</a>

				<a href="<?php echo esc_url( $accordion_url ); ?>" class="nlf-dashboard-card">
					<span class="dashicons dashicons-list-view nlf-dashboard-card__icon"></span>
					<h2 class="nlf-dashboard-card__title"><?php esc_html_e( 'Accordion Groups', 'krslys-next-level-faq-accordion' ); ?></h2>
					<p class="nlf-dashboard-card__meta">
						<?php
						printf(
							/* translators: %d: number of accordion groups */
							esc_html( _n( '%d group', '%d groups', $accordion_count, 'krslys-next-level-faq-accordion' ) ),
							(int) $accordion_count
						);
						?>
					</p>
					<p class="nlf-dashboard-card__desc"><?php esc_html_e( 'Create and manage your accordion sections.', 'krslys-next-level-faq-accordion' ); ?></p>
				</a>

				<a href="<?php echo esc_url( $tools_url ); ?>" class="nlf-dashboard-card">
					<span class="dashicons dashicons-admin-tools nlf-dashboard-card__icon"></span>
					<h2 class="nlf-dashboard-card__title"><?php esc_html_e( 'Tools', 'krslys-next-level-faq-accordion' ); ?></h2>
					<p class="nlf-dashboard-card__desc"><?php esc_html_e( 'Import and export your FAQ data for backup or migration.', 'krslys-next-level-faq-accordion' ); ?></p>
				</a>

			</div>

			<?php
			$schema_enabled = Settings_Repository::get_setting( Settings_Repository::KEY_SCHEMA_MARKUP, true );
			$settings_saved = isset( $_GET['settings-saved'] ) && '1' === $_GET['settings-saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice.
			?>
			<div class="nlf-dashboard-settings">
				<h2><?php esc_html_e( 'Settings', 'krslys-next-level-faq-accordion' ); ?></h2>

				<?php if ( $settings_saved ) : ?>
					<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'krslys-next-level-faq-accordion' ); ?></p></div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'nlf_faq_save_settings', 'nlf_settings_nonce' ); ?>
					<input type="hidden" name="action" value="nlf_faq_save_settings" />

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<?php esc_html_e( 'FAQPage Schema Markup', 'krslys-next-level-faq-accordion' ); ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="nlf_enable_schema_markup" value="1" <?php checked( $schema_enabled ); ?> />
									<?php esc_html_e( 'Enable FAQPage structured data (JSON-LD)', 'krslys-next-level-faq-accordion' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Adds schema.org/FAQPage structured data to help search engines display rich results for your FAQ sections.', 'krslys-next-level-faq-accordion' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Settings', 'krslys-next-level-faq-accordion' ) ); ?>
				</form>
			</div>

		</div>
		<?php
	}

	/**
	 * Render the Accordion Groups list table page.
	 *
	 * SECURITY: Capability check at start of function.
	 */
	/**
	 * Render FAQ groups list page.
	 */
	public static function render_faq_groups_page() {
		if ( ! self::current_user_can_manage() ) {
			return;
		}

		\Krslys\NextLevelFaqAccordion\Group_Admin::render_list_page( 'faq' );
	}

	/**
	 * Render accordion groups list page.
	 */
	public static function render_accordion_groups_page() {
		if ( ! self::current_user_can_manage() ) {
			return;
		}

		\Krslys\NextLevelFaqAccordion\Group_Admin::render_list_page( 'accordion' );
	}

	/**
	 * Handle saving global settings.
	 *
	 * @return void
	 */
	public static function handle_save_settings() {
		if ( ! self::current_user_can_manage() ) {
			wp_die( esc_html__( 'Unauthorized.', 'krslys-next-level-faq-accordion' ) );
		}

		check_admin_referer( 'nlf_faq_save_settings', 'nlf_settings_nonce' );

		$enable_schema = ! empty( $_POST['nlf_enable_schema_markup'] );
		Settings_Repository::update_setting( Settings_Repository::KEY_SCHEMA_MARKUP, $enable_schema );

		wp_safe_redirect( admin_url( 'admin.php?page=nlf-faq&settings-saved=1' ) );
		exit;
	}

	/**
	 * Render export/import tools page.
	 *
	 * SECURITY: Capability check at start of function.
	 *
	 * @return void
	 */
	public static function render_tools_page() {
		if ( ! self::current_user_can_manage() ) {
			return;
		}

		$groups = self::get_group_choices();
		?>
		<div class="wrap nlf-faq-admin nlf-faq-tools">

			<!-- ── Page Header ──────────────────────────── -->
			<div class="nlf-tools-header">
				<div class="nlf-tools-header__icon-wrap">
					<span class="dashicons dashicons-admin-tools"></span>
				</div>
				<div class="nlf-tools-header__content">
					<h1><?php esc_html_e( 'Tools', 'krslys-next-level-faq-accordion' ); ?></h1>
					<p><?php esc_html_e( 'Manage, backup, and migrate your FAQ data with powerful utilities.', 'krslys-next-level-faq-accordion' ); ?></p>
				</div>
			</div>

			<?php self::output_tools_notice(); ?>

			<!-- ── Data Management ──────────────────────── -->
			<div class="nlf-tools-section">
				<div class="nlf-tools-section__header">
					<span class="dashicons dashicons-database"></span>
					<div>
						<h2><?php esc_html_e( 'Data Management', 'krslys-next-level-faq-accordion' ); ?></h2>
						<p><?php esc_html_e( 'Export and import your FAQ content, themes, and settings.', 'krslys-next-level-faq-accordion' ); ?></p>
					</div>
				</div>

				<div class="nlf-tools-grid">

					<!-- ── Export Card ─────────────── -->
					<div class="nlf-tool-card nlf-tool-card--export">
						<div class="nlf-tool-card__accent"></div>
						<div class="nlf-tool-card__header">
							<div class="nlf-tool-card__icon">
								<span class="dashicons dashicons-download"></span>
							</div>
							<div>
								<h3><?php esc_html_e( 'Export', 'krslys-next-level-faq-accordion' ); ?></h3>
								<p><?php esc_html_e( 'Download a JSON file for backups or site migration.', 'krslys-next-level-faq-accordion' ); ?></p>
							</div>
						</div>
						<div class="nlf-tool-card__body">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nlf-tool-card__form">
								<?php wp_nonce_field( 'nlf_faq_export', 'nlf_faq_export_nonce' ); ?>
								<input type="hidden" name="action" value="nlf_faq_export" />

								<div class="nlf-tool-card__field">
									<label for="nlf-faq-export-scope" class="nlf-tool-card__field-label">
										<?php esc_html_e( 'Export scope', 'krslys-next-level-faq-accordion' ); ?>
									</label>
									<select id="nlf-faq-export-scope" name="nlf_faq_export_group" class="nlf-tool-card__select">
										<option value="all"><?php esc_html_e( 'All groups (full backup)', 'krslys-next-level-faq-accordion' ); ?></option>
										<?php foreach ( $groups as $value => $label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>">
												<?php echo esc_html( $label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div id="nlf-export-global-opts" class="nlf-tool-card__options">
									<label class="nlf-tool-card__option">
										<input type="checkbox" name="nlf_faq_include_styles" value="1" checked="checked" />
										<span><?php esc_html_e( 'Include style settings', 'krslys-next-level-faq-accordion' ); ?></span>
									</label>
									<label class="nlf-tool-card__option">
										<input type="checkbox" name="nlf_faq_include_questions" value="1" checked="checked" />
										<span><?php esc_html_e( 'Include FAQ entries', 'krslys-next-level-faq-accordion' ); ?></span>
									</label>
								</div>

								<p class="nlf-tool-card__hint" id="nlf-export-group-hint" style="display:none;">
									<?php esc_html_e( 'Exports the selected group with all its questions, theme, and settings.', 'krslys-next-level-faq-accordion' ); ?>
								</p>

								<?php submit_button( __( 'Download Export', 'krslys-next-level-faq-accordion' ), 'primary', 'submit', false ); ?>
							</form>
						</div>
					</div>

					<!-- ── Import Card ─────────────── -->
					<div class="nlf-tool-card nlf-tool-card--import">
						<div class="nlf-tool-card__accent"></div>
						<div class="nlf-tool-card__header">
							<div class="nlf-tool-card__icon">
								<span class="dashicons dashicons-upload"></span>
							</div>
							<div>
								<h3><?php esc_html_e( 'Import', 'krslys-next-level-faq-accordion' ); ?></h3>
								<p><?php esc_html_e( 'Upload a JSON file to restore FAQ data from a backup.', 'krslys-next-level-faq-accordion' ); ?></p>
							</div>
						</div>
						<div class="nlf-tool-card__body">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nlf-tool-card__form" enctype="multipart/form-data">
								<?php wp_nonce_field( 'nlf_faq_import', 'nlf_faq_import_nonce' ); ?>
								<input type="hidden" name="action" value="nlf_faq_import" />

								<div class="nlf-tool-card__field">
									<label for="nlf-faq-import-target" class="nlf-tool-card__field-label">
										<?php esc_html_e( 'Import target', 'krslys-next-level-faq-accordion' ); ?>
									</label>
									<select id="nlf-faq-import-target" name="nlf_faq_import_target" class="nlf-tool-card__select">
										<option value="all"><?php esc_html_e( 'Global (all FAQ data)', 'krslys-next-level-faq-accordion' ); ?></option>
										<option value="duplicate"><?php esc_html_e( 'Duplicate as new group', 'krslys-next-level-faq-accordion' ); ?></option>
										<?php foreach ( $groups as $value => $label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>">
												<?php echo esc_html( $label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<p class="nlf-tool-card__hint" id="nlf-import-duplicate-hint" style="display:none;">
									<?php esc_html_e( 'Creates a brand-new group from the exported file with all its questions, theme, and settings.', 'krslys-next-level-faq-accordion' ); ?>
								</p>

								<div class="nlf-tool-card__field">
									<label class="nlf-tool-card__field-label">
										<?php esc_html_e( 'Upload file', 'krslys-next-level-faq-accordion' ); ?>
									</label>
									<div class="nlf-file-zone" id="nlf-file-zone">
										<div class="nlf-file-zone__icon">
											<span class="dashicons dashicons-cloud-upload"></span>
										</div>
										<p class="nlf-file-zone__text">
											<?php esc_html_e( 'Drag & drop your file here or', 'krslys-next-level-faq-accordion' ); ?>
											<span class="nlf-file-zone__browse"><?php esc_html_e( 'browse', 'krslys-next-level-faq-accordion' ); ?></span>
										</p>
										<p class="nlf-file-zone__meta"><?php esc_html_e( 'Accepts .json files only', 'krslys-next-level-faq-accordion' ); ?></p>
										<input type="file" id="nlf-faq-import-file" name="nlf_faq_import_file" accept=".json,application/json" required />
									</div>
									<div class="nlf-file-info" id="nlf-file-info">
										<div class="nlf-file-info__icon">
											<span class="dashicons dashicons-media-code"></span>
										</div>
										<div class="nlf-file-info__details">
											<div class="nlf-file-info__name" id="nlf-file-name"></div>
											<div class="nlf-file-info__size" id="nlf-file-size"></div>
										</div>
										<button type="button" class="nlf-file-info__remove" id="nlf-file-remove" title="<?php esc_attr_e( 'Remove file', 'krslys-next-level-faq-accordion' ); ?>">&times;</button>
									</div>
								</div>

								<div id="nlf-import-replace-opt" class="nlf-tool-card__options">
									<label class="nlf-tool-card__option">
										<input type="checkbox" name="nlf_faq_replace_existing" value="1" />
										<span><?php esc_html_e( 'Replace existing items before import', 'krslys-next-level-faq-accordion' ); ?></span>
									</label>
								</div>

								<div id="nlf-import-group-opts" class="nlf-tool-card__options" style="display:none;">
									<label class="nlf-tool-card__option">
										<input type="checkbox" name="nlf_import_apply_styles" value="1" />
										<span><?php esc_html_e( 'Apply imported theme and styles to this group', 'krslys-next-level-faq-accordion' ); ?></span>
									</label>
								</div>

								<?php submit_button( __( 'Import', 'krslys-next-level-faq-accordion' ), 'primary', 'submit', false ); ?>
							</form>
						</div>
					</div>

				</div>
			</div>

			<!-- ── More Tools Coming Soon ───────────────── -->
			<div class="nlf-tools-section">
				<div class="nlf-tools-section__header">
					<span class="dashicons dashicons-superhero-alt"></span>
					<div>
						<h2><?php esc_html_e( 'More Tools', 'krslys-next-level-faq-accordion' ); ?></h2>
						<p><?php esc_html_e( 'Powerful utilities coming in future updates.', 'krslys-next-level-faq-accordion' ); ?></p>
					</div>
				</div>

				<div class="nlf-tools-grid">
					<div class="nlf-tool-card nlf-tool-card--placeholder">
						<div class="nlf-tool-card__accent"></div>
						<div class="nlf-tool-card__header">
							<div class="nlf-tool-card__icon">
								<span class="dashicons dashicons-image-rotate"></span>
							</div>
							<div>
								<h3>
									<?php esc_html_e( 'Reset', 'krslys-next-level-faq-accordion' ); ?>
									<span class="nlf-badge nlf-badge--soon"><?php esc_html_e( 'Soon', 'krslys-next-level-faq-accordion' ); ?></span>
								</h3>
								<p><?php esc_html_e( 'Selectively reset FAQ data, styles, or all plugin settings at once.', 'krslys-next-level-faq-accordion' ); ?></p>
							</div>
						</div>
					</div>

					<div class="nlf-tool-card nlf-tool-card--placeholder">
						<div class="nlf-tool-card__accent"></div>
						<div class="nlf-tool-card__header">
							<div class="nlf-tool-card__icon">
								<span class="dashicons dashicons-chart-bar"></span>
							</div>
							<div>
								<h3>
									<?php esc_html_e( 'Diagnostics', 'krslys-next-level-faq-accordion' ); ?>
									<span class="nlf-badge nlf-badge--soon"><?php esc_html_e( 'Soon', 'krslys-next-level-faq-accordion' ); ?></span>
								</h3>
								<p><?php esc_html_e( 'Analyze your FAQ setup and get optimization suggestions.', 'krslys-next-level-faq-accordion' ); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>

		</div>

		<?php
		wp_enqueue_script(
			'nlf-faq-admin-tools',
			krslys_nlfa_asset_url( 'assets/js/admin-faq-tools.js' ),
			array(),
			NLF_FAQ_VERSION,
			true
		);
		?>
		<?php
	}


	/**
	 * Export FAQ data as JSON.
	 *
	 * SECURITY:
	 * - Capability check: current_user_can('manage_options').
	 * - Nonce verification: check_admin_referer().
	 * - Output sanitization: wp_json_encode() handles escaping.
	 *
	 * @return void
	 */
	public static function handle_export() {
		if ( ! self::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to export FAQs.', 'krslys-next-level-faq-accordion' ) );
		}

		check_admin_referer( 'nlf_faq_export', 'nlf_faq_export_nonce' );

		$group_choice = isset( $_POST['nlf_faq_export_group'] )
			? sanitize_text_field( wp_unslash( $_POST['nlf_faq_export_group'] ) )
			: 'all';

		// ── Single-group export ──────────────────────────────
		if ( 'all' !== $group_choice && is_numeric( $group_choice ) ) {
			$group_id = absint( $group_choice );
			$payload  = self::build_group_export_payload( $group_id );

			if ( null === $payload ) {
				self::store_tools_notice( 'error', __( 'Unable to export this group. It may not exist.', 'krslys-next-level-faq-accordion' ) );
				wp_safe_redirect( self::get_tools_page_url() );
				exit;
			}

			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}

			$filename = sanitize_file_name( sprintf( 'faq-group-%d-%s.json', $group_id, gmdate( 'Ymd-His' ) ) );

			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML context.
			echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			exit;
		}

		// ── Global export ────────────────────────────────────
		$include_styles    = self::get_checkbox_state_from_post( 'nlf_faq_include_styles' );
		$include_questions = self::get_checkbox_state_from_post( 'nlf_faq_include_questions' );

		if ( ! $include_styles && ! $include_questions ) {
			self::store_tools_notice( 'error', __( 'Select at least one component to export.', 'krslys-next-level-faq-accordion' ) );
			wp_safe_redirect( self::get_tools_page_url() );
			exit;
		}

		$payload = array(
			'meta' => array(
				'schema'         => 'nlf-faq-tools.v1',
				'plugin_version'  => NLF_FAQ_VERSION,
				'schema_version'  => Database::get_schema_version(),
				'site_url'       => home_url(),
				'generated_at'   => gmdate( 'c' ),
			),
		);

		if ( $include_styles ) {
			$payload['styles'] = Options::get_options();
		}

		if ( $include_questions ) {
			$payload['meta']['group_scope']       = 'all';
			$payload['meta']['group_scope_label'] = __( 'All groups', 'krslys-next-level-faq-accordion' );
			$faqs              = self::group_faq_export_items( Repository::get_all_items_for_export( null ) );
			$payload['faqs']   = $faqs;
			$payload['groups'] = self::build_groups_meta_for_export( array_keys( $faqs ) );
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		$filename = sanitize_file_name( 'next-level-faq-export-' . gmdate( 'Ymd-His' ) . '.json' );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Export-Context: nlf-faq' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML context.
		echo wp_json_encode(
			$payload,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
		exit;
	}

	/**
	 * Import FAQ data from JSON.
	 *
	 * SECURITY:
	 * - Capability check: current_user_can('manage_options').
	 * - Nonce verification: check_admin_referer().
	 * - File upload validation: MIME type, extension, size checks.
	 * - Input sanitization: All imported data sanitized before use.
	 *
	 * @return void
	 */
	public static function handle_import() {
		if ( ! self::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to import FAQs.', 'krslys-next-level-faq-accordion' ) );
		}

		check_admin_referer( 'nlf_faq_import', 'nlf_faq_import_nonce' );

		$page_url     = self::get_tools_page_url();
		$import_target = isset( $_POST['nlf_faq_import_target'] )
			? sanitize_text_field( wp_unslash( $_POST['nlf_faq_import_target'] ) )
			: 'all';

		// ── Common file validation ───────────────────────────
		if ( empty( $_FILES['nlf_faq_import_file'] ) ) {
			self::store_tools_notice( 'error', __( 'Upload an export file before running import.', 'krslys-next-level-faq-accordion' ) );
			wp_safe_redirect( $page_url );
			exit;
		}

		$file = self::validate_json_file_upload( $_FILES['nlf_faq_import_file'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload array handled by validate_json_file_upload().

		if ( false === $file ) {
			if ( isset( $_FILES['nlf_faq_import_file']['error'] ) && (int) $_FILES['nlf_faq_import_file']['error'] !== UPLOAD_ERR_OK ) {
				self::store_tools_notice( 'error', self::describe_upload_error( (int) $_FILES['nlf_faq_import_file']['error'] ) );
			} elseif ( isset( $_FILES['nlf_faq_import_file']['size'] ) && (int) $_FILES['nlf_faq_import_file']['size'] > ( defined( 'MB_IN_BYTES' ) ? 2 * MB_IN_BYTES : 2 * 1024 * 1024 ) ) {
				self::store_tools_notice( 'error', __( 'Import file is too large. Please keep exports under 2MB.', 'krslys-next-level-faq-accordion' ) );
			} else {
				self::store_tools_notice( 'error', __( 'Only JSON files exported by this plugin are allowed.', 'krslys-next-level-faq-accordion' ) );
			}
			wp_safe_redirect( $page_url );
			exit;
		}

		$data = self::decode_import_file( $file['tmp_name'] );

		if ( null === $data ) {
			self::store_tools_notice( 'error', __( 'The uploaded file is not a valid export.', 'krslys-next-level-faq-accordion' ) );
			wp_safe_redirect( $page_url );
			exit;
		}

		$replace_existing = self::get_checkbox_state_from_post( 'nlf_faq_replace_existing' );

		// ── Duplicate as new group(s) ────────────────────────
		if ( 'duplicate' === $import_target ) {
			$has_single_items = ! empty( $data['items'] ) && is_array( $data['items'] );
			$has_global_faqs  = ! empty( $data['faqs'] ) && is_array( $data['faqs'] );

			if ( ! $has_single_items && ! $has_global_faqs ) {
				self::store_tools_notice( 'error', __( 'This file does not contain any FAQ data to duplicate.', 'krslys-next-level-faq-accordion' ) );
				wp_safe_redirect( $page_url );
				exit;
			}

			$groups_created = 0;
			$total_imported = 0;

			// ── Single-group export file (has 'items' key) ───
			if ( $has_single_items ) {
				$original_title = isset( $data['meta']['title'] ) ? sanitize_text_field( $data['meta']['title'] ) : '';

				$new_title = '' !== $original_title
					/* translators: %s: original FAQ group title. */
					? sprintf( __( '%s (Copy)', 'krslys-next-level-faq-accordion' ), $original_title )
					: __( 'Imported Group (Copy)', 'krslys-next-level-faq-accordion' );

				$original_type = isset( $data['meta']['type'] ) ? sanitize_key( $data['meta']['type'] ) : 'faq';

				$new_group_id = Groups_Repository::create_group(
					array(
						'title'  => $new_title,
						'type'   => $original_type,
						'status' => 'draft',
					)
				);

				if ( $new_group_id ) {
					$groups_created++;

					foreach ( $data['items'] as $index => $item ) {
						$question = isset( $item['question'] ) ? sanitize_text_field( $item['question'] ) : '';
						$answer   = isset( $item['answer'] ) ? wp_kses_post( $item['answer'] ) : '';

						if ( '' === trim( wp_strip_all_tags( $question ) ) && '' === trim( wp_strip_all_tags( $answer ) ) ) {
							continue;
						}

						Repository::save_item(
							0,
							$new_group_id,
							$question,
							$answer,
							isset( $item['status'] ) ? (int) $item['status'] : 1,
							$index,
							isset( $item['initial_state'] ) ? (int) $item['initial_state'] : 0,
							isset( $item['highlight'] ) ? (int) $item['highlight'] : 0
						);

						$total_imported++;
					}

					// Apply theme/settings from export.
					self::apply_group_meta_from_data( $new_group_id, $data );
					Cache::invalidate_group( $new_group_id );
				}
			}

			// ── Global export file (has 'faqs' key) ─────────
			if ( $has_global_faqs ) {
				// Per-group metadata written by the exporter; absent in export files
				// created before this field was added — treat those as empty arrays.
				$exported_group_meta = isset( $data['groups'] ) && is_array( $data['groups'] )
					? $data['groups']
					: array();

				foreach ( $data['faqs'] as $original_group_id => $items ) {
					if ( ! is_array( $items ) || empty( $items ) ) {
						continue;
					}

					// Use metadata from the export payload — never query the destination
					// DB by source ID, which would pull unrelated data on cross-site imports.
					$group_meta     = isset( $exported_group_meta[ (string) $original_group_id ] )
						&& is_array( $exported_group_meta[ (string) $original_group_id ] )
						? $exported_group_meta[ (string) $original_group_id ]
						: array();
					$original_title = isset( $group_meta['title'] )
						? sanitize_text_field( $group_meta['title'] )
						: '';

					$new_title = '' !== $original_title
						/* translators: %s: original FAQ group title. */
						? sprintf( __( '%s (Copy)', 'krslys-next-level-faq-accordion' ), $original_title )
						/* translators: %d: FAQ group ID */
						: sprintf( __( 'Group #%d (Copy)', 'krslys-next-level-faq-accordion' ), (int) $original_group_id );

					$create_data = array(
						'title'  => $new_title,
						'type'   => isset( $group_meta['type'] ) ? sanitize_key( $group_meta['type'] ) : 'faq',
						'status' => 'draft',
					);

					if ( ! empty( $group_meta['theme_settings'] ) && is_array( $group_meta['theme_settings'] ) ) {
						$create_data['theme_settings'] = $group_meta['theme_settings'];
					}
					if ( ! empty( $group_meta['display_settings'] ) && is_array( $group_meta['display_settings'] ) ) {
						$create_data['display_settings'] = $group_meta['display_settings'];
					}
					if ( isset( $group_meta['use_custom_style'] ) ) {
						$create_data['use_custom_style'] = (bool) $group_meta['use_custom_style'];
					}
					if ( ! empty( $group_meta['custom_styles'] ) && is_array( $group_meta['custom_styles'] ) ) {
						$create_data['custom_styles'] = $group_meta['custom_styles'];
					}

					$new_group_id = Groups_Repository::create_group( $create_data );

					if ( ! $new_group_id ) {
						continue;
					}

					$groups_created++;

					foreach ( $items as $index => $item ) {
						if ( ! is_array( $item ) ) {
							continue;
						}

						$question = isset( $item['question'] ) ? sanitize_text_field( $item['question'] ) : '';
						$answer   = isset( $item['answer'] ) ? wp_kses_post( $item['answer'] ) : '';

						if ( '' === trim( wp_strip_all_tags( $question ) ) && '' === trim( wp_strip_all_tags( $answer ) ) ) {
							continue;
						}

						Repository::save_item(
							0,
							$new_group_id,
							$question,
							$answer,
							isset( $item['status'] ) ? (int) $item['status'] : 1,
							isset( $item['position'] ) ? (int) $item['position'] : $index,
							isset( $item['initial_state'] ) ? (int) $item['initial_state'] : 0,
							isset( $item['highlight'] ) ? (int) $item['highlight'] : 0
						);

						$total_imported++;
					}

					Cache::invalidate_group( $new_group_id );
				}
			}

			if ( 0 === $groups_created ) {
				self::store_tools_notice( 'error', __( 'Failed to create any new groups.', 'krslys-next-level-faq-accordion' ) );
				wp_safe_redirect( $page_url );
				exit;
			}

			$message = sprintf(
				/* translators: 1: number of groups created, 2: number of items */
				_n(
					'%1$d new group created with %2$d FAQ items. Saved as draft.',
					'%1$d new groups created with %2$d FAQ items. All saved as drafts.',
					$groups_created,
					'krslys-next-level-faq-accordion'
				),
				$groups_created,
				$total_imported
			);

			self::store_tools_notice( 'success', $message );
			wp_safe_redirect( $page_url );
			exit;
		}

		// ── Single-group import ──────────────────────────────
		if ( 'all' !== $import_target && is_numeric( $import_target ) ) {
			$group_id = absint( $import_target );

			$group = Groups_Repository::get_group_by_id( $group_id );
			if ( ! $group ) {
				self::store_tools_notice( 'error', __( 'The selected group does not exist.', 'krslys-next-level-faq-accordion' ) );
				wp_safe_redirect( $page_url );
				exit;
			}

			$apply_styles = ! empty( $_POST['nlf_import_apply_styles'] );

			if ( $replace_existing ) {
				Repository::delete_items_for_group( $group_id );
			}

			$imported = 0;

			if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
				foreach ( $data['items'] as $index => $item ) {
					$question = isset( $item['question'] ) ? sanitize_text_field( $item['question'] ) : '';
					$answer   = isset( $item['answer'] ) ? wp_kses_post( $item['answer'] ) : '';

					if ( '' === trim( wp_strip_all_tags( $question ) ) && '' === trim( wp_strip_all_tags( $answer ) ) ) {
						continue;
					}

					Repository::save_item(
						0,
						$group_id,
						$question,
						$answer,
						isset( $item['status'] ) ? (int) $item['status'] : 1,
						$index,
						isset( $item['initial_state'] ) ? (int) $item['initial_state'] : 0,
						isset( $item['highlight'] ) ? (int) $item['highlight'] : 0
					);

					$imported++;
				}
			}

			if ( $apply_styles ) {
				self::apply_group_meta_from_data( $group_id, $data );
			}

			Cache::invalidate_group( $group_id );

			$message_bits = array();

			if ( $imported > 0 ) {
				$message_bits[] = sprintf(
					/* translators: %d: number of imported FAQs */
					_n( '%d FAQ item imported into group.', '%d FAQ items imported into group.', $imported, 'krslys-next-level-faq-accordion' ),
					$imported
				);
			}

			if ( $apply_styles ) {
				$message_bits[] = __( 'Group theme and styles applied.', 'krslys-next-level-faq-accordion' );
			}

			if ( empty( $message_bits ) ) {
				self::store_tools_notice( 'warning', __( 'No items were imported. The file may be empty or contain no valid entries.', 'krslys-next-level-faq-accordion' ) );
			} else {
				self::store_tools_notice( 'success', implode( ' ', $message_bits ) );
			}

			wp_safe_redirect( $page_url );
			exit;
		}

		// ── Global import ────────────────────────────────────
		$imported_count = 0;
		$styles_applied = false;

		$faq_entries = self::normalize_import_faqs( $data['faqs'] ?? array() );
		if ( ! empty( $faq_entries ) ) {
			if ( $replace_existing ) {
				Repository::delete_all_items();
			}

			foreach ( $faq_entries as $index => $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$question = isset( $item['question'] ) ? sanitize_text_field( $item['question'] ) : '';
				$answer   = isset( $item['answer'] ) ? wp_kses_post( $item['answer'] ) : '';

				if ( '' === trim( wp_strip_all_tags( $question ) ) && '' === trim( wp_strip_all_tags( $answer ) ) ) {
					continue;
				}

				$group_id      = isset( $item['group_id'] ) ? absint( $item['group_id'] ) : 0;
				$position      = isset( $item['position'] ) ? absint( $item['position'] ) : (int) $index;
				$status        = isset( $item['status'] ) ? absint( $item['status'] ) : 0;
				$initial_state = isset( $item['initial_state'] ) ? absint( $item['initial_state'] ) : 0;
				$highlight     = isset( $item['highlight'] ) ? absint( $item['highlight'] ) : 0;

				Repository::save_item(
					0,
					$group_id,
					$question,
					$answer,
					$status,
					$position,
					$initial_state,
					$highlight
				);

				$imported_count++;
			}
		}

		if ( isset( $data['styles'] ) && is_array( $data['styles'] ) ) {
			$sanitized = Options::sanitize( $data['styles'] );
			Settings_Repository::update_setting( Settings_Repository::KEY_GLOBAL_STYLES, $sanitized );
			$styles_applied = true;
		}

		if ( 0 === $imported_count && ! $styles_applied ) {
			self::store_tools_notice( 'error', __( 'Nothing was imported. Ensure the file contains FAQ entries or style settings.', 'krslys-next-level-faq-accordion' ) );
			wp_safe_redirect( $page_url );
			exit;
		}

		$message_bits = array();

		if ( $imported_count > 0 ) {
			$message_bits[] = sprintf(
				/* translators: %d: number of imported FAQs */
				_n( '%d FAQ item imported.', '%d FAQ items imported.', $imported_count, 'krslys-next-level-faq-accordion' ),
				$imported_count
			);
		}

		if ( $styles_applied ) {
			$message_bits[] = __( 'Style settings synced.', 'krslys-next-level-faq-accordion' );
		}

		self::store_tools_notice( 'success', implode( ' ', $message_bits ) );
		wp_safe_redirect( $page_url );
		exit;
	}

	/**
	 * Build export payload for a single group.
	 *
	 * @param int $group_id Group ID.
	 * @return array|null Payload array or null if group not found.
	 */
	private static function build_group_export_payload( $group_id ) {
		$group = Groups_Repository::get_group_by_id( $group_id );

		if ( ! $group ) {
			return null;
		}

		$items = Repository::get_items_for_group( $group_id, false );

		return array(
			'meta'             => array(
				'id'           => $group_id,
				'title'        => $group->title,
				'generated_at' => gmdate( 'c' ),
			),
			'theme'            => $group->theme_settings['theme'] ?? '',
			'theme_custom'     => $group->theme_settings['custom_colors'] ?? '',
			'settings'         => $group->display_settings,
			'use_custom_style' => (bool) $group->use_custom_style,
			'custom_styles'    => $group->custom_styles,
			'items'            => array_map(
				static function ( $item ) {
					return array(
						'question'      => $item->question,
						'answer'        => $item->answer,
						'status'        => (int) $item->status,
						'initial_state' => (int) $item->initial_state,
						'highlight'     => (int) $item->highlight,
					);
				},
				$items ?: array()
			),
		);
	}

	/**
	 * Apply theme/settings from export data to a group in the custom table.
	 *
	 * Used by both "Duplicate as new group" and "Import into group" with apply styles.
	 *
	 * @param int   $group_id Target group ID.
	 * @param array $data     Decoded export data.
	 */
	private static function apply_group_meta_from_data( $group_id, $data ) {
		$update = array();

		// Build theme_settings from export data.
		$theme_settings = array();
		if ( isset( $data['theme'] ) ) {
			$theme_settings['theme'] = sanitize_key( $data['theme'] );
		}
		if ( isset( $data['theme_custom'] ) && is_array( $data['theme_custom'] ) ) {
			$theme_settings['custom_colors'] = array_map( 'sanitize_hex_color', $data['theme_custom'] );
		}
		if ( ! empty( $theme_settings ) ) {
			$update['theme_settings'] = $theme_settings;
		}

		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$settings           = $data['settings'];
			$sanitized_settings = array(
				'accordion_mode'  => ! empty( $settings['accordion_mode'] ),
				'initial_state'   => in_array( $settings['initial_state'] ?? '', array( 'all_closed', 'first_open', 'custom' ), true ) ? $settings['initial_state'] : 'all_closed',
				'animation_speed' => in_array( $settings['animation_speed'] ?? '', array( 'fast', 'normal', 'slow' ), true ) ? $settings['animation_speed'] : 'normal',
				'show_search'     => ! empty( $settings['show_search'] ),
				'show_counter'    => ! empty( $settings['show_counter'] ),
				'smooth_scroll'   => ! empty( $settings['smooth_scroll'] ),
			);
			$update['display_settings'] = $sanitized_settings;
		}

		if ( isset( $data['use_custom_style'] ) ) {
			$update['use_custom_style'] = ! empty( $data['use_custom_style'] );
		}

		if ( isset( $data['custom_styles'] ) && is_array( $data['custom_styles'] ) ) {
			$sanitized_styles       = Options::sanitize( $data['custom_styles'] );
			$update['custom_styles'] = $sanitized_styles;
		}

		if ( ! empty( $update ) ) {
			Groups_Repository::update_group( $group_id, $update );
		}

		if ( isset( $sanitized_styles ) && class_exists( 'Krslys\NextLevelFaqAccordion\Style_Generator' ) ) {
			Style_Generator::generate_and_save_for_group( $group_id, $sanitized_styles );
		}
	}

	/**
	 * Persist notice data between redirects.
	 *
	 * SECURITY: Message is sanitized via wp_strip_all_tags().
	 *
	 * @param string $type    Notice severity.
	 * @param string $message Message text.
	 *
	 * @return void
	 */
	private static function store_tools_notice( $type, $message ) {
		$allowed = array( 'success', 'error', 'warning', 'info' );
		$type    = in_array( $type, $allowed, true ) ? $type : 'info';

		set_transient(
			self::get_tools_notice_key(),
			array(
				'type'    => $type,
				'message' => wp_strip_all_tags( (string) $message ),
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Output notice stored in transient.
	 *
	 * SECURITY: Output escaped via esc_attr() and esc_html().
	 *
	 * @return void
	 */
	private static function output_tools_notice() {
		$notice = self::consume_tools_notice();

		if ( null === $notice ) {
			return;
		}

		$class_map = array(
			'success' => 'notice-success',
			'error'   => 'notice-error',
			'warning' => 'notice-warning',
			'info'    => 'notice-info',
		);

		printf(
			'<div class="notice %1$s"><p>%2$s</p></div>',
			esc_attr( $class_map[ $notice['type'] ] ?? 'notice-info' ),
			esc_html( $notice['message'] )
		);
	}

	/**
	 * Retrieve and clear stored notice.
	 *
	 * @return array|null
	 */
	private static function consume_tools_notice() {
		$key    = self::get_tools_notice_key();
		$notice = get_transient( $key );

		if ( false === $notice || ! is_array( $notice ) ) {
			return null;
		}

		delete_transient( $key );

		if ( empty( $notice['type'] ) || empty( $notice['message'] ) ) {
			return null;
		}

		return array(
			'type'    => sanitize_key( $notice['type'] ),
			'message' => (string) $notice['message'],
		);
	}

	/**
	 * Build URL to tools page.
	 *
	 * @return string
	 */
	private static function get_tools_page_url() {
		return add_query_arg(
			array(
				'page' => self::TOOLS_SLUG,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Validate JSON file upload with comprehensive checks.
	 *
	 * SECURITY:
	 * - Validates file upload errors
	 * - Validates file size (2MB limit)
	 * - Validates file extension (.json)
	 * - Uses is_valid_json_upload() for MIME and content validation
	 *
	 * @param array  $file      Upload file array from $_FILES.
	 * @param string $error_key Optional error key for storing notices.
	 * @return array|false Returns file array on success, false on failure.
	 */
	public static function validate_json_file_upload( $file, $error_key = 'import_error' ) {
		if ( empty( $file ) || empty( $file['tmp_name'] ) ) {
			return false;
		}

		$filename = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';

		if ( isset( $file['error'] ) && (int) $file['error'] !== UPLOAD_ERR_OK ) {
			return false;
		}

		$size_limit = defined( 'MB_IN_BYTES' ) ? 2 * MB_IN_BYTES : 2 * 1024 * 1024;
		if ( isset( $file['size'] ) && (int) $file['size'] > $size_limit ) {
			return false;
		}

		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'json' !== $ext ) {
			return false;
		}

		if ( ! self::is_valid_json_upload( $file ) ) {
			return false;
		}

		return $file;
	}

	/**
	 * Human readable upload error.
	 *
	 * @param int $code PHP upload error code.
	 *
	 * @return string
	 */
	public static function describe_upload_error( $code ) {
		switch ( (int) $code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'The uploaded file exceeds the maximum allowed size.', 'krslys-next-level-faq-accordion' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'The uploaded file was only partially uploaded. Please try again.', 'krslys-next-level-faq-accordion' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'No file was uploaded.', 'krslys-next-level-faq-accordion' );
			case UPLOAD_ERR_NO_TMP_DIR:
				return __( 'Server configuration error: missing a temporary folder.', 'krslys-next-level-faq-accordion' );
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'Server error: failed to write file to disk.', 'krslys-next-level-faq-accordion' );
			case UPLOAD_ERR_EXTENSION:
				return __( 'A PHP extension stopped the file upload.', 'krslys-next-level-faq-accordion' );
			default:
				return __( 'Unexpected upload error occurred.', 'krslys-next-level-faq-accordion' );
		}
	}

	/**
	 * Decode JSON import file using WordPress Filesystem API.
	 *
	 * SECURITY: Uses WordPress native functions for safe file reading.
	 *
	 * @param string $file_path Path to uploaded file.
	 *
	 * @return array|null
	 */
	public static function decode_import_file( $file_path ) {
		if ( ! is_readable( $file_path ) ) {
			return null;
		}

		// Try wp_json_file_decode if available (WordPress 5.9+).
		if ( function_exists( 'wp_json_file_decode' ) ) {
			$data = wp_json_file_decode(
				$file_path,
				array(
					'associative' => true,
				)
			);

			return is_array( $data ) ? $data : null;
		}

		// Fallback: Use WP_Filesystem API.
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();

		if ( ! $wp_filesystem || ! $wp_filesystem->exists( $file_path ) ) {
			return null;
		}

		$contents = $wp_filesystem->get_contents( $file_path );

		if ( false === $contents ) {
			return null;
		}

		$data = json_decode( $contents, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Transient key for notices.
	 *
	 * @return string
	 */
	private static function get_tools_notice_key() {
		return 'nlf_faq_tools_notice_' . get_current_user_id();
	}


	/**
	 * Format FAQ export items grouped by group ID.
	 *
	 * @param array $items Flat list of FAQ rows.
	 * @return array
	 */
	private static function group_faq_export_items( $items ) {
		if ( empty( $items ) || ! is_array( $items ) ) {
			return array();
		}

		$grouped = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$group_id = isset( $item['group_id'] ) ? (int) $item['group_id'] : 0;
			$key      = (string) $group_id;

			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = array();
			}

			$grouped[ $key ][] = $item;
		}

		ksort( $grouped, SORT_NUMERIC );

		return $grouped;
	}

	/**
	 * Build per-group metadata for the global export payload.
	 *
	 * Keyed by group ID (string) so the importer can look up metadata without
	 * querying the destination database.
	 *
	 * @param string[] $group_ids String group IDs from the faqs map.
	 * @return array<string, array>
	 */
	private static function build_groups_meta_for_export( array $group_ids ) {
		$meta = array();

		foreach ( $group_ids as $group_id ) {
			$group_id = (int) $group_id;
			$group    = Groups_Repository::get_group_by_id( $group_id );

			if ( ! $group ) {
				continue;
			}

			$meta[ (string) $group_id ] = array(
				'title'            => $group->title,
				'theme_settings'   => $group->theme_settings,
				'display_settings' => $group->display_settings,
				'use_custom_style' => (bool) $group->use_custom_style,
				'custom_styles'    => $group->custom_styles,
			);
		}

		return $meta;
	}

	/**
	 * Read a checkbox-like value from $_POST and normalize to bool.
	 *
	 * SECURITY: Validates and sanitizes checkbox input.
	 *
	 * @param string $key Checkbox key.
	 * @return bool
	 */
	private static function get_checkbox_state_from_post( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in calling method.
		if ( ! isset( $_POST[ $key ] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in calling method.
		$value = wp_unslash( $_POST[ $key ] );

		// Handle array values (e.g. checkboxes with [] names).
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		$value = sanitize_text_field( $value );

		// Use filter_var for proper boolean validation.
		$validated = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

		if ( null !== $validated ) {
			return $validated;
		}

		return ! empty( $value );
	}

	/**
	 * Comprehensive JSON upload validation.
	 *
	 * SECURITY:
	 * - Validates MIME type using finfo.
	 * - Validates file extension against allowlist.
	 * - Validates file content starts with JSON structure.
	 * - Protects against directory traversal attacks.
	 *
	 * @param array $file Upload file array from $_FILES.
	 * @return bool
	 */
	public static function is_valid_json_upload( $file ) {
		if ( empty( $file['tmp_name'] ) || ! is_readable( $file['tmp_name'] ) ) {
		return false;
	}

	$filename = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( 'json' !== $ext ) {
		return false;
	}

	$allowed_mimes = array(
			'application/json',
			'text/json',
			'text/plain', // Some servers report JSON as text/plain.
		);

		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			if ( $finfo ) {
				$mime = finfo_file( $finfo, $file['tmp_name'] );
				finfo_close( $finfo );

				if ( $mime && ! in_array( $mime, $allowed_mimes, true ) ) {
					return false;
				}
		}
	}

	global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();

		if ( ! $wp_filesystem || ! $wp_filesystem->exists( $file['tmp_name'] ) ) {
			return false;
		}

		// Read first few bytes to check for JSON structure.
		$prefix = $wp_filesystem->get_contents( $file['tmp_name'] );

		if ( false === $prefix ) {
			return false;
		}

	// Get first 10 characters after trimming whitespace.
	$prefix = ltrim( substr( $prefix, 0, 10 ) );

	return '' !== $prefix && in_array( $prefix[0], array( '{', '[' ), true );
	}

	/**
	 * Flatten imported FAQ structures into a simple list.
	 *
	 * Supports both legacy flat arrays and new grouped objects.
	 *
	 * @param mixed $faqs_raw Raw FAQs block.
	 * @return array
	 */
	private static function normalize_import_faqs( $faqs_raw ) {
		if ( empty( $faqs_raw ) || ! is_array( $faqs_raw ) ) {
			return array();
		}

		if ( self::is_sequential_array( $faqs_raw ) ) {
			return array_values( $faqs_raw );
		}

		$normalized = array();

		foreach ( $faqs_raw as $group_id => $records ) {
			if ( ! is_array( $records ) ) {
				continue;
			}

			foreach ( $records as $record ) {
				if ( ! is_array( $record ) ) {
					continue;
				}

				if ( ! isset( $record['group_id'] ) && is_numeric( $group_id ) ) {
					$record['group_id'] = (int) $group_id;
				}

				$normalized[] = $record;
			}
		}

		return $normalized;
	}

	/**
	 * Check whether an array is sequential (0..n).
	 *
	 * @param array $array Array to inspect.
	 * @return bool
	 */
	private static function is_sequential_array( $array ) {
		if ( empty( $array ) || ! is_array( $array ) ) {
			return true;
		}

		$expected = 0;

		foreach ( $array as $key => $_value ) {
			if ( (string) (int) $key !== (string) $key || (int) $key !== $expected ) {
				return false;
			}

			$expected++;
		}

		return true;
	}


	/**
	 * Get FAQ group choices from the custom groups table.
	 *
	 * Returns an array of group_id => title for all groups.
	 *
	 * @return array
	 */
	private static function get_group_choices() {
		$choices = array();

		$groups = Groups_Repository::get_all_groups( null, 'title', 'ASC' );

		foreach ( $groups as $group ) {
			$title = trim( $group->title );

			$choices[ (string) $group->id ] = '' !== $title
				? $title
				/* translators: %d: FAQ group ID */
				: sprintf( __( 'Group #%d', 'krslys-next-level-faq-accordion' ), (int) $group->id );
		}

		return $choices;
	}
}
