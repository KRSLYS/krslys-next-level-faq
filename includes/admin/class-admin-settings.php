<?php
/**
 * Admin settings page and assets.
 *
 * @package Krslys\NextLevelFaq
 */

namespace Krslys\NextLevelFaq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings page and assets.
 *
 * SECURITY FEATURES:
 * - All admin actions require 'manage_options' capability.
 * - All forms protected with nonce verification.
 * - File uploads thoroughly validated (MIME type, size, extension).
 * - All inputs sanitized, all outputs escaped.
 * - Uses WordPress Filesystem API for file operations.
 */
class Admin_Settings {

	/**
	 * Top-level menu slug.
	 */
	const TOP_MENU_SLUG = 'nlf-faq';

	/**
	 * Style page slug.
	 */
	const STYLE_SLUG = 'nlf-faq-style';

	/**
	 * Questions page slug.
	 */
	const QUESTIONS_SLUG = 'nlf-faq-questions';

	/**
	 * Tools page slug.
	 */
	const TOOLS_SLUG = 'nlf-faq-tools';

	/**
	 * Register admin menu.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Next Level FAQ', 'next-level-faq' ),
			__( 'FAQs', 'next-level-faq' ),
			'manage_options',
			self::TOP_MENU_SLUG,
			array( __CLASS__, 'render_style_page' ),
			'dashicons-editor-help',
			26
		);

		add_submenu_page(
			self::TOP_MENU_SLUG,
			__( 'FAQ Style & Layout', 'next-level-faq' ),
			__( 'Style & Layout', 'next-level-faq' ),
			'manage_options',
			self::STYLE_SLUG,
			array( __CLASS__, 'render_style_page' )
		);

		add_submenu_page(
			self::TOP_MENU_SLUG,
			__( 'FAQ Tools', 'next-level-faq' ),
			__( 'Tools', 'next-level-faq' ),
			'manage_options',
			self::TOOLS_SLUG,
			array( __CLASS__, 'render_tools_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public static function register_settings() {
		register_setting(
			'nlf_faq_style_group',
			Options::OPTION_KEY,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_and_save_to_repository' ),
			)
		);

		// Register AJAX handler for instant save
		add_action( 'wp_ajax_nlf_save_settings_ajax', array( __CLASS__, 'handle_ajax_save_settings' ) );
	}

	/**
	 * Sanitize and save settings to custom table.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized input (still needed for WordPress form flow).
	 */
	public static function sanitize_and_save_to_repository( $input ) {
		$sanitized = Options::sanitize( $input );
		
		// Save to custom settings table
		Settings_Repository::update_setting( Settings_Repository::KEY_GLOBAL_STYLES, $sanitized );
		
		// Trigger action for CSS regeneration
		do_action( 'nlf_faq_settings_updated', $sanitized, null );
		
		return $sanitized;
	}

	/**
	 * Handle AJAX save request for settings.
	 *
	 * SECURITY:
	 * - Nonce verification
	 * - Capability check
	 * - Input sanitization via Options::sanitize()
	 */
	public static function handle_ajax_save_settings() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nlf_save_settings' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'next-level-faq' ) ),
				403
			);
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to save settings.', 'next-level-faq' ) ),
				403
			);
		}

		// Get and sanitize input
		$raw_input = isset( $_POST[ Options::OPTION_KEY ] ) ? wp_unslash( $_POST[ Options::OPTION_KEY ] ) : array();
		
		if ( ! is_array( $raw_input ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid settings data.', 'next-level-faq' ) ),
				400
			);
		}

		// Sanitize using Options class
		$sanitized = Options::sanitize( $raw_input );

		// Ensure tables exist before saving.
		if ( ! Database::tables_exist() ) {
			Database::create_tables( true ); // Force creation
			
			// Double check after creation
			if ( ! Database::tables_exist() ) {
				global $wpdb;
				wp_send_json_error(
					array( 
						'message' => __( 'Database tables could not be created. Please check database permissions.', 'next-level-faq' ),
						'debug'   => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? array(
							'last_error' => $wpdb->last_error,
							'prefix'     => $wpdb->prefix,
						) : null,
					),
					500
				);
			}
		}

		// Save to custom settings table
		$saved = Settings_Repository::update_setting( Settings_Repository::KEY_GLOBAL_STYLES, $sanitized );

		if ( ! $saved ) {
			// Get last database error for debugging
			global $wpdb;
			$db_error = $wpdb->last_error ? $wpdb->last_error : 'Unknown database error';
			
			wp_send_json_error(
				array( 
					'message' => __( 'Failed to save settings. Please try again.', 'next-level-faq' ),
					'debug'   => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? array(
						'db_error' => $db_error,
						'table'    => Database::get_settings_table(),
						'key'      => Settings_Repository::KEY_GLOBAL_STYLES,
					) : null,
				),
				500
			);
		}

		// Regenerate CSS
		if ( class_exists( 'Krslys\NextLevelFaq\Style_Generator' ) ) {
			Style_Generator::generate_and_save();
		}

		// Trigger action for extensions/integrations
		do_action( 'nlf_faq_settings_updated', $sanitized, null );

		// Send success response
		wp_send_json_success(
			array(
				'message' => __( 'Settings saved successfully!', 'next-level-faq' ),
				'data'    => $sanitized,
			)
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
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}

		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );

		$allowed_pages = array(
			self::STYLE_SLUG,
			self::QUESTIONS_SLUG,
			self::TOP_MENU_SLUG,
			self::TOOLS_SLUG,
		);

		if ( ! in_array( $page, $allowed_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'nlf-faq-admin',
			NLF_FAQ_PLUGIN_URL . 'assets/css/admin-faq-style.css',
			array(),
			NLF_FAQ_VERSION
		);

		// Enqueue generated CSS for style page preview.
		if ( in_array( $page, array( self::STYLE_SLUG, self::TOP_MENU_SLUG ), true ) ) {
			$css_path = Style_Generator::get_css_file_path();
			$css_url  = Style_Generator::get_css_file_url();
			if ( $css_url && $css_path && file_exists( $css_path ) ) {
				wp_enqueue_style(
					'nlf-faq-generated',
					esc_url_raw( $css_url ),
					array( 'nlf-faq-admin' ),
					filemtime( $css_path )
				);
			}
		}

		// Enqueue WordPress color picker for style page only.
		if ( in_array( $page, array( self::STYLE_SLUG, self::TOP_MENU_SLUG ), true ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script(
				'nlf-faq-admin',
				NLF_FAQ_PLUGIN_URL . 'assets/js/admin-faq-style.js',
				array( 'jquery', 'wp-color-picker' ),
				NLF_FAQ_VERSION,
				true
			);
		} else {
			wp_enqueue_script(
				'nlf-faq-admin',
				NLF_FAQ_PLUGIN_URL . 'assets/js/admin-faq-style.js',
				array( 'jquery' ),
				NLF_FAQ_VERSION,
				true
			);
		}

		wp_localize_script(
			'nlf-faq-admin',
			'nlfFaqAdmin',
			array(
				'i18n' => array(
					'saving' => __( 'Saving…', 'next-level-faq' ),
					'saved'  => __( 'Saved', 'next-level-faq' ),
				),
				'presets'        => Options::get_preset_registry(),
				'activePreset'   => Options::get_active_preset_slug( Options::get_options() ),
				'defaultPreset'  => Options::get_default_preset_slug(),
				'optionKey'      => Options::OPTION_KEY,
				'currentOptions' => Options::get_resolved_options(),
				'saveNonce'      => wp_create_nonce( 'nlf_save_settings' ),
			)
		);
	}

	/**
	 * Render style settings page.
	 *
	 * SECURITY: Capability check at start of function.
	 */
	public static function render_style_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options       = Options::get_resolved_options();
		$presets       = Options::get_preset_registry();
		$active_preset = Options::get_active_preset_slug( $options );
		?>
		<div class="wrap nlf-faq-admin">
			<h1><?php esc_html_e( 'Next Level FAQ – Style & Layout', 'next-level-faq' ); ?></h1>

			<div class="nlf-faq-admin__layout">
				<div class="nlf-faq-admin__left">
					<div class="nlf-section">
						<div class="nlf-section-header">
							<h2><?php esc_html_e( 'Theme presets', 'next-level-faq' ); ?></h2>
							<p class="description"><?php esc_html_e( 'Pick a curated starting point, then fine-tune colors, spacing, and typography below.', 'next-level-faq' ); ?></p>
						</div>
						<div class="nlf-theme-grid" id="nlf-preset-grid" data-current-preset="<?php echo esc_attr( $active_preset ); ?>">
							<?php foreach ( $presets as $slug => $preset ) : ?>
								<?php $values = $preset['values']; ?>
								<label class="nlf-theme-card nlf-preset-card <?php echo esc_attr( $active_preset === $slug ? 'active' : '' ); ?>">
									<input type="radio"
										name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[preset]"
										value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( $active_preset, $slug ); ?>
										data-preset-choice
									/>
									<span class="screen-reader-text"><?php echo esc_html( $preset['name'] ); ?></span>
									<div class="nlf-theme-preview" style="
										background: <?php echo esc_attr( $values['container_background'] ); ?>;
										border-color: <?php echo esc_attr( $values['container_border_color'] ); ?>;
										color: <?php echo esc_attr( $values['answer_color'] ); ?>;">
										<div class="nlf-theme-preview-question" style="color: <?php echo esc_attr( $values['question_color'] ); ?>;">
											<?php esc_html_e( 'Sample question?', 'next-level-faq' ); ?>
										</div>
										<div class="nlf-theme-preview-answer">
											<?php esc_html_e( 'Sample answer text…', 'next-level-faq' ); ?>
										</div>
										<div class="nlf-theme-preview-accent" style="background: <?php echo esc_attr( $values['accent_color'] ); ?>;"></div>
									</div>
									<div class="nlf-theme-name"><?php echo esc_html( $preset['name'] ); ?></div>
									<p class="description" style="margin:0; padding: 0 var(--spacing-3) var(--spacing-3);"><?php echo esc_html( $preset['description'] ); ?></p>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

				<form method="post" action="options.php" id="nlf-faq-style-form">
					<?php
					settings_fields( 'nlf_faq_style_group' );
					
					// Force redirect back to this specific page after save.
					// Override the default _wp_http_referer that settings_fields() creates.
					$settings_redirect = add_query_arg(
						array(
							'page' => self::STYLE_SLUG,
						),
						admin_url( 'admin.php' )
					);
					?>
					<input type="hidden" name="_wp_http_referer" value="<?php echo esc_url( $settings_redirect ); ?>" />
					
					<!-- Hidden field to persist preset selection (synced via JS) -->
					<input type="hidden" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[preset]" id="nlf-faq-hidden-preset" value="<?php echo esc_attr( $active_preset ); ?>" />

					<h2><?php esc_html_e( 'Layout & Container', 'next-level-faq' ); ?></h2>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="nlf_faq_container_background"><?php esc_html_e( 'Container background', 'next-level-faq' ); ?></label>
								</th>
								<td>
									<input type="text" class="nlf-color-field" id="nlf_faq_container_background" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[container_background]" value="<?php echo esc_attr( $options['container_background'] ); ?>" data-preview-prop="container_background">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="nlf_faq_container_border_color"><?php esc_html_e( 'Border color', 'next-level-faq' ); ?></label>
								</th>
								<td>
									<input type="text" class="nlf-color-field" id="nlf_faq_container_border_color" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[container_border_color]" value="<?php echo esc_attr( $options['container_border_color'] ); ?>" data-preview-prop="container_border_color">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="nlf_faq_container_border_radius"><?php esc_html_e( 'Border radius (px)', 'next-level-faq' ); ?></label>
								</th>
								<td>
									<input type="number" min="0" id="nlf_faq_container_border_radius" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[container_border_radius]" value="<?php echo esc_attr( $options['container_border_radius'] ); ?>" data-preview-prop="container_border_radius">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="nlf_faq_container_padding"><?php esc_html_e( 'Padding (px)', 'next-level-faq' ); ?></label>
								</th>
								<td>
									<input type="number" min="0" id="nlf_faq_container_padding" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[container_padding]" value="<?php echo esc_attr( $options['container_padding'] ); ?>" data-preview-prop="container_padding">
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Shadow', 'next-level-faq' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[shadow]" value="1" <?php checked( $options['shadow'], true ); ?> data-preview-prop="shadow">
										<?php esc_html_e( 'Enable subtle card shadow', 'next-level-faq' ); ?>
									</label>
								</td>
							</tr>
						</table>

						<h2><?php esc_html_e( 'Question', 'next-level-faq' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="nlf_faq_question_color"><?php esc_html_e( 'Question color', 'next-level-faq' ); ?></label>
								</th>
								<td>
									<input type="text" class="nlf-color-field" id="nlf_faq_question_color" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[question_color]" value="<?php echo esc_attr( $options['question_color'] ); ?>" data-preview-prop="question_color">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="nlf_faq_question_font_size"><?php esc_html_e( 'Font size (px)', 'next-level-faq' ); ?></label>
								</th>
								<td>
									<input type="number" min="10" id="nlf_faq_question_font_size" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[question_font_size]" value="<?php echo esc_attr( $options['question_font_size'] ); ?>" data-preview-prop="question_font_size">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="nlf_faq_question_font_weight"><?php esc_html_e( 'Font weight', 'next-level-faq' ); ?></label>
								</th>
								<td>
									<input type="number" step="100" min="100" max="900" id="nlf_faq_question_font_weight" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[question_font_weight]" value="<?php echo esc_attr( $options['question_font_weight'] ); ?>" data-preview-prop="question_font_weight">
								</td>
							</tr>
						</table>

						<h2><?php esc_html_e( 'Answer', 'next-level-faq' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="nlf_faq_answer_color"><?php esc_html_e( 'Answer color', 'next-level-faq' ); ?></label>
								</th>
								<td>
									<input type="text" class="nlf-color-field" id="nlf_faq_answer_color" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[answer_color]" value="<?php echo esc_attr( $options['answer_color'] ); ?>" data-preview-prop="answer_color">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="nlf_faq_answer_font_size"><?php esc_html_e( 'Font size (px)', 'next-level-faq' ); ?></label>
								</th>
								<td>
									<input type="number" min="10" id="nlf_faq_answer_font_size" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[answer_font_size]" value="<?php echo esc_attr( $options['answer_font_size'] ); ?>" data-preview-prop="answer_font_size">
								</td>
							</tr>
						</table>

						<h2><?php esc_html_e( 'Accent & Behavior', 'next-level-faq' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="nlf_faq_accent_color"><?php esc_html_e( 'Accent color', 'next-level-faq' ); ?></label>
								</th>
								<td>
									<input type="text" class="nlf-color-field" id="nlf_faq_accent_color" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[accent_color]" value="<?php echo esc_attr( $options['accent_color'] ); ?>" data-preview-prop="accent_color">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="nlf_faq_icon_style"><?php esc_html_e( 'Icon style', 'next-level-faq' ); ?></label>
								</th>
								<td>
									<select id="nlf_faq_icon_style" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[icon_style]" data-preview-prop="icon_style">
										<option value="plus_minus" <?php selected( $options['icon_style'], 'plus_minus' ); ?>><?php esc_html_e( 'Plus / Minus', 'next-level-faq' ); ?></option>
										<option value="chevron" <?php selected( $options['icon_style'], 'chevron' ); ?>><?php esc_html_e( 'Chevron', 'next-level-faq' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="nlf_faq_gap_between_items"><?php esc_html_e( 'Gap between items (px)', 'next-level-faq' ); ?></label>
								</th>
								<td>
									<input type="number" min="0" id="nlf_faq_gap_between_items" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[gap_between_items]" value="<?php echo esc_attr( $options['gap_between_items'] ); ?>" data-preview-prop="gap_between_items">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="nlf_faq_animation"><?php esc_html_e( 'Animation', 'next-level-faq' ); ?></label>
								</th>
								<td>
									<select id="nlf_faq_animation" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[animation]" data-preview-prop="animation">
										<option value="slide" <?php selected( $options['animation'], 'slide' ); ?>><?php esc_html_e( 'Slide', 'next-level-faq' ); ?></option>
										<option value="fade" <?php selected( $options['animation'], 'fade' ); ?>><?php esc_html_e( 'Fade', 'next-level-faq' ); ?></option>
										<option value="none" <?php selected( $options['animation'], 'none' ); ?>><?php esc_html_e( 'None', 'next-level-faq' ); ?></option>
									</select>
								</td>
							</tr>
						</table>

						<?php submit_button( __( 'Save Styles', 'next-level-faq' ) ); ?>
					</form>
				</div>

				<div class="nlf-faq-admin__right">
					<?php Admin_UI_Components::mobile_preview_notice(); ?>
					<h2><?php esc_html_e( 'Live Preview', 'next-level-faq' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Preview shows how your FAQ will look with the current style settings.', 'next-level-faq' ); ?></p>

					<div id="nlf-faq-preview-root"
						data-container-background="<?php echo esc_attr( $options['container_background'] ); ?>"
						data-container-border-color="<?php echo esc_attr( $options['container_border_color'] ); ?>"
						data-container-border-radius="<?php echo esc_attr( $options['container_border_radius'] ); ?>"
						data-container-padding="<?php echo esc_attr( $options['container_padding'] ); ?>"
						data-question-color="<?php echo esc_attr( $options['question_color'] ); ?>"
						data-question-font-size="<?php echo esc_attr( $options['question_font_size'] ); ?>"
						data-question-font-weight="<?php echo esc_attr( $options['question_font_weight'] ); ?>"
						data-answer-color="<?php echo esc_attr( $options['answer_color'] ); ?>"
						data-answer-font-size="<?php echo esc_attr( $options['answer_font_size'] ); ?>"
						data-accent-color="<?php echo esc_attr( $options['accent_color'] ); ?>"
						data-gap-between-items="<?php echo esc_attr( $options['gap_between_items'] ); ?>"
						data-shadow="<?php echo esc_attr( $options['shadow'] ? '1' : '0' ); ?>"
						data-icon-style="<?php echo esc_attr( $options['icon_style'] ); ?>"
						data-animation="<?php echo esc_attr( $options['animation'] ); ?>"
						data-preset="<?php echo esc_attr( $active_preset ); ?>"
					>
						<div class="nlf-faq nlf-faq--preview">
							<div class="nlf-faq__item is-open">
								<div class="nlf-faq__question">
									<span><?php esc_html_e( 'How quickly can I customize my FAQs?', 'next-level-faq' ); ?></span>
									<span class="nlf-faq__icon" aria-hidden="true"></span>
								</div>
								<div class="nlf-faq__answer">
									<p><?php esc_html_e( 'Changes you make here are applied instantly and reflected on the front-end as soon as you save.', 'next-level-faq' ); ?></p>
								</div>
							</div>
							<div class="nlf-faq__item">
								<div class="nlf-faq__question">
									<span><?php esc_html_e( 'Can I match my brand colors?', 'next-level-faq' ); ?></span>
									<span class="nlf-faq__icon" aria-hidden="true"></span>
								</div>
								<div class="nlf-faq__answer">
									<p><?php esc_html_e( 'Yes. Configure colors, typography, spacing, and animations to align with your brand.', 'next-level-faq' ); ?></p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}


	/**
	 * Render export/import tools page.
	 *
	 * SECURITY: Capability check at start of function.
	 *
	 * @return void
	 */
	public static function render_tools_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$group_choices   = self::get_group_choices();
		$selected_group  = 'all';
		?>
		<div class="wrap nlf-faq-admin nlf-faq-tools">
			<h1><?php esc_html_e( 'Next Level FAQ – Tools', 'next-level-faq' ); ?></h1>
			<?php self::output_tools_notice(); ?>

			<div class="nlf-faq-tools__grid">
				<section class="nlf-faq-tools__card">
					<h2><?php esc_html_e( 'Export', 'next-level-faq' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Generate a JSON bundle with your FAQ groups, styles and entries for backups or migrations.', 'next-level-faq' ); ?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nlf-faq-tools__form">
						<?php wp_nonce_field( 'nlf_faq_export', 'nlf_faq_export_nonce' ); ?>
						<input type="hidden" name="action" value="nlf_faq_export" />

						<label class="nlf-faq-tools__option">
							<input type="checkbox" name="nlf_faq_include_styles" value="1" checked="checked" />
							<span><?php esc_html_e( 'Include style settings', 'next-level-faq' ); ?></span>
						</label>

						<label class="nlf-faq-tools__option">
							<input type="checkbox" name="nlf_faq_include_questions" value="1" checked="checked" />
							<span><?php esc_html_e( 'Include FAQ entries', 'next-level-faq' ); ?></span>
						</label>

						<label for="nlf-faq-export-group" class="nlf-faq-tools__field-label">
							<?php esc_html_e( 'Limit FAQ export to a specific group', 'next-level-faq' ); ?>
						</label>
						<select id="nlf-faq-export-group" name="nlf_faq_export_group" class="nlf-faq-tools__select">
							<?php foreach ( $group_choices as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $selected_group ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Choose "All groups" to export every FAQ entry.', 'next-level-faq' ); ?>
						</p>

						<?php submit_button( __( 'Download Export', 'next-level-faq' ), 'primary', 'submit', false ); ?>
					</form>
				</section>

				<section class="nlf-faq-tools__card">
					<h2><?php esc_html_e( 'Import', 'next-level-faq' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Upload an export file from this or another site to synchronize FAQs safely.', 'next-level-faq' ); ?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nlf-faq-tools__form" enctype="multipart/form-data">
						<?php wp_nonce_field( 'nlf_faq_import', 'nlf_faq_import_nonce' ); ?>
						<input type="hidden" name="action" value="nlf_faq_import" />

						<label for="nlf-faq-import-file" class="nlf-faq-tools__field-label">
							<?php esc_html_e( 'Select export file (.json)', 'next-level-faq' ); ?>
						</label>
						<input type="file" id="nlf-faq-import-file" name="nlf_faq_import_file" accept=".json,application/json" required />

						<label class="nlf-faq-tools__option">
							<input type="checkbox" name="nlf_faq_replace_existing" value="1" />
							<span><?php esc_html_e( 'Replace current FAQ entries before import', 'next-level-faq' ); ?></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enable this to wipe the FAQ table before importing to avoid duplicates.', 'next-level-faq' ); ?>
						</p>

						<?php submit_button( __( 'Import Package', 'next-level-faq' ), 'primary', 'submit', false ); ?>
					</form>
				</section>
			</div>

			<h2 style="margin-top: 2em;"><?php esc_html_e( 'Single Group Tools', 'next-level-faq' ); ?></h2>
			<p class="description" style="margin-bottom: 1em;">
				<?php esc_html_e( 'Export or import a single FAQ group with its questions, theme, and settings.', 'next-level-faq' ); ?>
			</p>

			<div class="nlf-faq-tools__grid">
				<section class="nlf-faq-tools__card">
					<h2><?php esc_html_e( 'Export Group', 'next-level-faq' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Download a JSON snapshot containing a single group\'s questions, theme, and settings.', 'next-level-faq' ); ?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nlf-faq-tools__form">
						<?php wp_nonce_field( 'nlf_export_single_group', 'nlf_export_single_group_nonce' ); ?>
						<input type="hidden" name="action" value="nlf_export_single_group" />

						<label for="nlf-faq-export-single-group" class="nlf-faq-tools__field-label">
							<?php esc_html_e( 'Select a group to export', 'next-level-faq' ); ?>
						</label>
						<select id="nlf-faq-export-single-group" name="group_id" class="nlf-faq-tools__select">
							<?php
							$groups_only = $group_choices;
							unset( $groups_only['all'] );
							if ( empty( $groups_only ) ) :
							?>
								<option value=""><?php esc_html_e( '— No groups available —', 'next-level-faq' ); ?></option>
							<?php else : ?>
								<?php foreach ( $groups_only as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>">
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>

						<?php submit_button( __( 'Export Group (.json)', 'next-level-faq' ), 'primary', 'submit', false, empty( $groups_only ) ? array( 'disabled' => 'disabled' ) : array() ); ?>
					</form>
				</section>

				<section class="nlf-faq-tools__card">
					<h2><?php esc_html_e( 'Import into Group', 'next-level-faq' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Upload a JSON file exported from another FAQ group to merge or replace content.', 'next-level-faq' ); ?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nlf-faq-tools__form" enctype="multipart/form-data">
						<?php wp_nonce_field( 'nlf_import_single_group', 'nlf_import_single_group_nonce' ); ?>
						<input type="hidden" name="action" value="nlf_import_single_group" />

						<label for="nlf-faq-import-single-group" class="nlf-faq-tools__field-label">
							<?php esc_html_e( 'Select target group', 'next-level-faq' ); ?>
						</label>
						<select id="nlf-faq-import-single-group" name="group_id" class="nlf-faq-tools__select">
							<?php if ( empty( $groups_only ) ) : ?>
								<option value=""><?php esc_html_e( '— No groups available —', 'next-level-faq' ); ?></option>
							<?php else : ?>
								<?php foreach ( $groups_only as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>">
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>

						<label for="nlf-faq-group-import-file" class="nlf-faq-tools__field-label">
							<?php esc_html_e( 'Select export file (.json)', 'next-level-faq' ); ?>
						</label>
						<input type="file" id="nlf-faq-group-import-file" name="nlf_group_import_file" accept=".json,application/json" required />

						<label class="nlf-faq-tools__option">
							<input type="checkbox" name="nlf_import_replace" value="1" />
							<span><?php esc_html_e( 'Replace existing items before import', 'next-level-faq' ); ?></span>
						</label>

						<label class="nlf-faq-tools__option">
							<input type="checkbox" name="nlf_import_apply_styles" value="1" />
							<span><?php esc_html_e( 'Apply imported theme and styles to this group', 'next-level-faq' ); ?></span>
						</label>

						<?php submit_button( __( 'Import Group JSON', 'next-level-faq' ), 'primary', 'submit', false, empty( $groups_only ) ? array( 'disabled' => 'disabled' ) : array() ); ?>
					</form>
				</section>
			</div>
		</div>
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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export FAQs.', 'next-level-faq' ) );
		}

		check_admin_referer( 'nlf_faq_export', 'nlf_faq_export_nonce' );

		$include_styles    = self::get_checkbox_state_from_post( 'nlf_faq_include_styles' );
		$include_questions = self::get_checkbox_state_from_post( 'nlf_faq_include_questions' );
		$group_choice      = isset( $_POST['nlf_faq_export_group'] )
			? sanitize_text_field( wp_unslash( $_POST['nlf_faq_export_group'] ) )
			: 'all';
		$group_scope       = self::normalize_group_choice( $group_choice );

		if ( ! $include_styles && ! $include_questions ) {
			self::store_tools_notice( 'error', __( 'Select at least one component to export.', 'next-level-faq' ) );
			wp_safe_redirect( self::get_tools_page_url() );
			exit;
		}

		$payload = array(
			'meta' => array(
				'schema'         => 'nlf-faq-tools.v1',
				'plugin_version' => NLF_FAQ_VERSION,
				'db_version'     => get_option( 'nlf_faq_db_version', NLF_FAQ_DB_VERSION ),
				'site_url'       => home_url(),
				'generated_at'   => gmdate( 'c' ),
			),
		);

		if ( $include_styles ) {
			$payload['styles'] = Options::get_options();
		}

		if ( $include_questions ) {
			$payload['meta']['group_scope']       = null === $group_scope ? 'all' : (int) $group_scope;
			$payload['meta']['group_scope_label'] = self::get_group_label( $group_scope );
			$payload['faqs']                      = self::group_faq_export_items(
				Repository::get_all_items_for_export( $group_scope )
			);
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		$filename_parts = array( 'next-level-faq-export' );

		if ( $include_questions && null !== $group_scope ) {
			$filename_parts[] = 'group-' . (int) $group_scope;
		}

		$filename = sanitize_file_name( implode( '-', $filename_parts ) . '-' . gmdate( 'Ymd-His' ) . '.json' );

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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to import FAQs.', 'next-level-faq' ) );
		}

		check_admin_referer( 'nlf_faq_import', 'nlf_faq_import_nonce' );

		$page_url = self::get_tools_page_url();

		if ( empty( $_FILES['nlf_faq_import_file'] ) ) {
			self::store_tools_notice( 'error', __( 'Upload an export file before running import.', 'next-level-faq' ) );
			wp_safe_redirect( $page_url );
			exit;
		}

		$file = self::validate_json_file_upload( $_FILES['nlf_faq_import_file'] );

		if ( false === $file ) {
			if ( isset( $_FILES['nlf_faq_import_file']['error'] ) && (int) $_FILES['nlf_faq_import_file']['error'] !== UPLOAD_ERR_OK ) {
				self::store_tools_notice( 'error', self::describe_upload_error( (int) $_FILES['nlf_faq_import_file']['error'] ) );
			} elseif ( isset( $_FILES['nlf_faq_import_file']['size'] ) && (int) $_FILES['nlf_faq_import_file']['size'] > ( defined( 'MB_IN_BYTES' ) ? 2 * MB_IN_BYTES : 2 * 1024 * 1024 ) ) {
				self::store_tools_notice( 'error', __( 'Import file is too large. Please keep exports under 2MB.', 'next-level-faq' ) );
			} else {
				self::store_tools_notice( 'error', __( 'Only JSON files exported by this plugin are allowed.', 'next-level-faq' ) );
			}
			wp_safe_redirect( $page_url );
			exit;
		}

		$data = self::decode_import_file( $file['tmp_name'] );

		if ( null === $data ) {
			self::store_tools_notice( 'error', __( 'The uploaded file is not a valid export.', 'next-level-faq' ) );
			wp_safe_redirect( $page_url );
			exit;
		}

		$replace_existing = self::get_checkbox_state_from_post( 'nlf_faq_replace_existing' );
		$imported_count   = 0;
		$styles_applied   = false;

		$faq_entries = self::normalize_import_faqs( $data['faqs'] ?? array() );
		if ( ! empty( $faq_entries ) ) {
			if ( $replace_existing ) {
				Repository::delete_all_items();
			}

			foreach ( $faq_entries as $index => $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				// Sanitize inputs to prevent XSS - match normal save_metabox() behavior.
				$question = isset( $item['question'] ) ? sanitize_text_field( $item['question'] ) : '';
				$answer   = isset( $item['answer'] ) ? wp_kses_post( $item['answer'] ) : '';

				$trimmed_question = trim( wp_strip_all_tags( $question ) );
				$trimmed_answer   = trim( wp_strip_all_tags( $answer ) );

				if ( '' === $trimmed_question && '' === $trimmed_answer ) {
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
			self::store_tools_notice( 'error', __( 'Nothing was imported. Ensure the file contains FAQ entries or style settings.', 'next-level-faq' ) );
			wp_safe_redirect( $page_url );
			exit;
		}

		$message_bits = array();

		if ( $imported_count > 0 ) {
			$message_bits[] = sprintf(
				/* translators: %d: number of imported FAQs */
				_n( '%d FAQ item imported.', '%d FAQ items imported.', $imported_count, 'next-level-faq' ),
				$imported_count
			);
		}

		if ( $styles_applied ) {
			$message_bits[] = __( 'Style settings synced.', 'next-level-faq' );
		}

		self::store_tools_notice( 'success', implode( ' ', $message_bits ) );
		wp_safe_redirect( $page_url );
		exit;
	}

	/**
	 * Export a single FAQ group to JSON.
	 *
	 * SECURITY:
	 * - Capability check: current_user_can('manage_options').
	 * - Nonce verification: check_admin_referer().
	 *
	 * @return void
	 */
	public static function handle_group_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export FAQs.', 'next-level-faq' ) );
		}

		if ( ! isset( $_POST['group_id'], $_POST['nlf_export_single_group_nonce'] ) ) {
			wp_die( esc_html__( 'Invalid export request.', 'next-level-faq' ) );
		}

		$group_id = absint( $_POST['group_id'] );

		check_admin_referer( 'nlf_export_single_group', 'nlf_export_single_group_nonce' );

		if ( ! $group_id ) {
			self::store_tools_notice( 'error', __( 'Please select a group to export.', 'next-level-faq' ) );
			wp_safe_redirect( self::get_tools_page_url() );
			exit;
		}

		$payload = self::build_group_export_payload( $group_id );

		if ( null === $payload ) {
			self::store_tools_notice( 'error', __( 'Unable to export this group. It may not exist.', 'next-level-faq' ) );
			wp_safe_redirect( self::get_tools_page_url() );
			exit;
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		$filename = sprintf( 'faq-group-%d-%s.json', $group_id, gmdate( 'Ymd-His' ) );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML context.
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Import JSON payload into a single FAQ group.
	 *
	 * SECURITY:
	 * - Capability check: current_user_can('manage_options').
	 * - Nonce verification: check_admin_referer().
	 * - File upload validation.
	 * - Input sanitization.
	 *
	 * @return void
	 */
	public static function handle_group_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to import FAQs.', 'next-level-faq' ) );
		}

		if ( ! isset( $_POST['group_id'], $_POST['nlf_import_single_group_nonce'] ) ) {
			wp_die( esc_html__( 'Invalid import request.', 'next-level-faq' ) );
		}

		$group_id = absint( $_POST['group_id'] );
		$page_url = self::get_tools_page_url();

		check_admin_referer( 'nlf_import_single_group', 'nlf_import_single_group_nonce' );

		if ( ! $group_id ) {
			self::store_tools_notice( 'error', __( 'Please select a target group for import.', 'next-level-faq' ) );
			wp_safe_redirect( $page_url );
			exit;
		}

		$post = get_post( $group_id );
		if ( ! $post || Group_CPT::POST_TYPE !== $post->post_type ) {
			self::store_tools_notice( 'error', __( 'The selected group does not exist.', 'next-level-faq' ) );
			wp_safe_redirect( $page_url );
			exit;
		}

		if ( empty( $_FILES['nlf_group_import_file'] ) ) {
			self::store_tools_notice( 'error', __( 'Upload failed. Please select a valid JSON file.', 'next-level-faq' ) );
			wp_safe_redirect( $page_url );
			exit;
		}

		$file = self::validate_json_file_upload( $_FILES['nlf_group_import_file'] );

		if ( false === $file ) {
			$error_message = __( 'Invalid file upload. Please ensure the file is a valid JSON export under 2MB.', 'next-level-faq' );
			if ( isset( $_FILES['nlf_group_import_file']['error'] ) && (int) $_FILES['nlf_group_import_file']['error'] !== UPLOAD_ERR_OK ) {
				$error_message = self::describe_upload_error( (int) $_FILES['nlf_group_import_file']['error'] );
			}
			self::store_tools_notice( 'error', $error_message );
			wp_safe_redirect( $page_url );
			exit;
		}

		$data = self::decode_import_file( $file['tmp_name'] );

		if ( null === $data || ! is_array( $data ) ) {
			self::store_tools_notice( 'error', __( 'Invalid JSON file.', 'next-level-faq' ) );
			wp_safe_redirect( $page_url );
			exit;
		}

		$replace_items = ! empty( $_POST['nlf_import_replace'] );
		$apply_styles  = ! empty( $_POST['nlf_import_apply_styles'] );

		if ( $replace_items ) {
			Repository::delete_items_for_group( $group_id );
		}

		$imported = 0;

		if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as $index => $item ) {
				// Sanitize inputs to prevent XSS.
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
			if ( isset( $data['theme'] ) ) {
				update_post_meta( $group_id, '_nlf_faq_group_theme', sanitize_key( $data['theme'] ) );
			}

			if ( isset( $data['theme_custom'] ) && is_array( $data['theme_custom'] ) ) {
				update_post_meta( $group_id, '_nlf_faq_group_theme_custom', array_map( 'sanitize_hex_color', $data['theme_custom'] ) );
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
				update_post_meta( $group_id, '_nlf_faq_group_settings', $sanitized_settings );
			}

			if ( isset( $data['use_custom_style'] ) ) {
				update_post_meta( $group_id, '_nlf_faq_group_use_custom_style', ! empty( $data['use_custom_style'] ) );
			}

			if ( isset( $data['custom_styles'] ) && is_array( $data['custom_styles'] ) ) {
				$sanitized_styles = Options::sanitize( $data['custom_styles'] );
				update_post_meta( $group_id, '_nlf_faq_group_custom_styles', $sanitized_styles );
				if ( class_exists( 'Krslys\NextLevelFaq\Style_Generator' ) ) {
					Style_Generator::generate_and_save_for_group( $group_id, $sanitized_styles );
				}
			}
		}

		Cache::invalidate_group( $group_id );

		$message_bits = array();

		if ( $imported > 0 ) {
			$message_bits[] = sprintf(
				/* translators: %d: number of imported FAQs */
				_n( '%d FAQ item imported into group.', '%d FAQ items imported into group.', $imported, 'next-level-faq' ),
				$imported
			);
		}

		if ( $apply_styles ) {
			$message_bits[] = __( 'Group theme and styles applied.', 'next-level-faq' );
		}

		if ( empty( $message_bits ) ) {
			self::store_tools_notice( 'warning', __( 'No items were imported. The file may be empty or contain no valid entries.', 'next-level-faq' ) );
		} else {
			self::store_tools_notice( 'success', implode( ' ', $message_bits ) );
		}

		wp_safe_redirect( $page_url );
		exit;
	}

	/**
	 * Build export payload for a single group.
	 *
	 * @param int $group_id Group post ID.
	 * @return array|null Payload array or null if group not found.
	 */
	private static function build_group_export_payload( $group_id ) {
		$post = get_post( $group_id );

		if ( ! $post || Group_CPT::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$items = Repository::get_items_for_group( $group_id, false );

		return array(
			'meta'             => array(
				'id'           => $group_id,
				'title'        => get_the_title( $post ),
				'generated_at' => gmdate( 'c' ),
			),
			'theme'            => get_post_meta( $group_id, '_nlf_faq_group_theme', true ),
			'theme_custom'     => get_post_meta( $group_id, '_nlf_faq_group_theme_custom', true ),
			'settings'         => get_post_meta( $group_id, '_nlf_faq_group_settings', true ),
			'use_custom_style' => (bool) get_post_meta( $group_id, '_nlf_faq_group_use_custom_style', true ),
			'custom_styles'    => get_post_meta( $group_id, '_nlf_faq_group_custom_styles', true ),
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
				return __( 'The uploaded file exceeds the maximum allowed size.', 'next-level-faq' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'The uploaded file was only partially uploaded. Please try again.', 'next-level-faq' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'No file was uploaded.', 'next-level-faq' );
			case UPLOAD_ERR_NO_TMP_DIR:
				return __( 'Server configuration error: missing a temporary folder.', 'next-level-faq' );
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'Server error: failed to write file to disk.', 'next-level-faq' );
			case UPLOAD_ERR_EXTENSION:
				return __( 'A PHP extension stopped the file upload.', 'next-level-faq' );
			default:
				return __( 'Unexpected upload error occurred.', 'next-level-faq' );
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
	 * Normalize select value into group scope.
	 *
	 * @param string $choice Raw select value.
	 * @return int|null
	 */
	private static function normalize_group_choice( $choice ) {
		if ( 'all' === $choice || '' === $choice ) {
			return null;
		}

		if ( ! is_numeric( $choice ) ) {
			return null;
		}

		return max( 0, (int) $choice );
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
	 * Read a checkbox-like value from $_POST and normalize to bool.
	 *
	 * SECURITY: Validates and sanitizes checkbox input.
	 *
	 * @param string $key Checkbox key.
	 * @return bool
	 */
	private static function get_checkbox_state_from_post( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return false;
		}

		$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );

		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

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
	 * Options for the export group selector.
	 *
	 * @return array
	 */
	private static function get_group_choices() {
		$choices = array(
			'all' => __( 'All groups', 'next-level-faq' ),
		);

		$groups = Groups_Repository::get_all_groups( 'active', 'title', 'ASC' );

		foreach ( $groups as $group ) {
			$title = trim( $group->title );

			$choices[ (string) $group->id ] = '' !== $title
				? $title
				: sprintf( __( 'Group #%d', 'next-level-faq' ), (int) $group->id );
		}

		return $choices;
	}

	/**
	 * Retrieve human label for a group scope.
	 *
	 * @param int|null $group_id Group ID.
	 * @return string
	 */
	private static function get_group_label( $group_id ) {
		if ( null === $group_id ) {
			return __( 'All groups', 'next-level-faq' );
		}

		$group = Groups_Repository::get_group_by_id( (int) $group_id );

		if ( $group ) {
			$title = trim( $group->title );

			return '' !== $title
				? $title
				: sprintf( __( 'Group #%d', 'next-level-faq' ), (int) $group_id );
		}

		return sprintf( __( 'Group #%d', 'next-level-faq' ), (int) $group_id );
	}
}
