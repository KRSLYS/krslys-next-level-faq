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
						'debug'   => ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG )
						? array( 'last_error' => $wpdb->last_error )
						: null,
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
					'debug'   => ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG )
					? array( 'db_error' => $db_error )
					: null,
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
			nlf_asset_url( 'assets/css/admin-faq-style.css' ),
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
				nlf_asset_url( 'assets/js/admin-faq-style.js' ),
				array( 'jquery', 'wp-color-picker' ),
				NLF_FAQ_VERSION,
				true
			);
		} else {
			wp_enqueue_script(
				'nlf-faq-admin',
				nlf_asset_url( 'assets/js/admin-faq-style.js' ),
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
						<?php
						$layout_labels = array(
							'flat'     => __( 'Flat', 'next-level-faq' ),
							'cards'    => __( 'Cards', 'next-level-faq' ),
							'bordered' => __( 'Bordered', 'next-level-faq' ),
							'clean'    => __( 'Clean', 'next-level-faq' ),
							'striped'  => __( 'Striped', 'next-level-faq' ),
						);
						?>
						<div class="nlf-theme-grid" id="nlf-preset-grid" data-current-preset="<?php echo esc_attr( $active_preset ); ?>">
							<?php foreach ( $presets as $slug => $preset ) :
								$values    = $preset['values'];
								$p_layout  = $values['layout'] ?? 'flat';
								$is_cards  = 'cards' === $p_layout;
								$p_radius  = ( $values['container_border_radius'] ?? 8 ) . 'px';
								$has_shadow = ! empty( $values['shadow'] ) && false !== $values['shadow'];
								$p_shadow  = $has_shadow ? '0 2px 8px rgba(0,0,0,0.08)' : 'none';
							?>
								<label class="nlf-theme-card nlf-preset-card <?php echo esc_attr( $active_preset === $slug ? 'active' : '' ); ?>">
									<input type="radio"
										name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[preset]"
										value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( $active_preset, $slug ); ?>
										data-preset-choice
									/>
									<span class="screen-reader-text"><?php echo esc_html( $preset['name'] ); ?></span>
									<div class="nlf-theme-preview nlf-theme-preview--<?php echo esc_attr( $p_layout ); ?>" style="
										background: <?php echo $is_cards ? 'transparent' : esc_attr( $values['container_background'] ); ?>;
										border-color: <?php echo $is_cards ? 'transparent' : esc_attr( $values['container_border_color'] ); ?>;
										border-radius: <?php echo esc_attr( $p_radius ); ?>;
										box-shadow: <?php echo $is_cards ? 'none' : esc_attr( $p_shadow ); ?>;
										color: <?php echo esc_attr( $values['answer_color'] ); ?>;">
										<div class="nlf-theme-preview-item" style="
											background: <?php echo $is_cards ? esc_attr( $values['container_background'] ) : 'transparent'; ?>;
											border: <?php echo $is_cards ? '1px solid ' . esc_attr( $values['container_border_color'] ) : 'none'; ?>;
											border-radius: <?php echo $is_cards ? esc_attr( $p_radius ) : '0'; ?>;
											border-bottom: <?php echo ! $is_cards ? '1px solid ' . esc_attr( $values['container_border_color'] ) : 'none'; ?>;
											box-shadow: <?php echo $is_cards ? esc_attr( $p_shadow ) : 'none'; ?>;
											padding: 8px <?php echo $is_cards ? '10px' : '2px'; ?>;
										">
											<div class="nlf-theme-preview-question" style="color: <?php echo esc_attr( $values['question_color'] ); ?>;">
												<?php esc_html_e( 'Sample question?', 'next-level-faq' ); ?>
											</div>
											<div class="nlf-theme-preview-answer">
												<?php esc_html_e( 'Sample answer text...', 'next-level-faq' ); ?>
											</div>
										</div>
										<div class="nlf-theme-preview-item nlf-theme-preview-item--collapsed" style="
											background: <?php echo $is_cards ? esc_attr( $values['container_background'] ) : 'transparent'; ?>;
											border: <?php echo $is_cards ? '1px solid ' . esc_attr( $values['container_border_color'] ) : 'none'; ?>;
											border-radius: <?php echo $is_cards ? esc_attr( $p_radius ) : '0'; ?>;
											padding: 8px <?php echo $is_cards ? '10px' : '2px'; ?>;
										">
											<div class="nlf-theme-preview-question" style="color: <?php echo esc_attr( $values['question_color'] ); ?>; opacity: 0.65;">
												<?php esc_html_e( 'Another question?', 'next-level-faq' ); ?>
											</div>
										</div>
										<div class="nlf-theme-preview-accent" style="background: <?php echo esc_attr( $values['accent_color'] ); ?>;"></div>
									</div>
									<div class="nlf-theme-name"><?php echo esc_html( $preset['name'] ); ?></div>
									<p class="description" style="margin:0; padding: 0 var(--spacing-3) var(--spacing-3);"><?php echo esc_html( $preset['description'] ); ?></p>
									<span class="nlf-theme-layout-tag" style="margin: 0 var(--spacing-3) var(--spacing-3);"><?php echo esc_html( $layout_labels[ $p_layout ] ?? $p_layout ); ?></span>
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
									<option value="arrow" <?php selected( $options['icon_style'], 'arrow' ); ?>><?php esc_html_e( 'Arrow', 'next-level-faq' ); ?></option>
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
						<tr>
							<th scope="row">
								<label for="nlf_faq_layout"><?php esc_html_e( 'Layout style', 'next-level-faq' ); ?></label>
							</th>
							<td>
								<select id="nlf_faq_layout" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[layout]" data-preview-prop="layout">
									<option value="flat" <?php selected( $options['layout'] ?? 'flat', 'flat' ); ?>><?php esc_html_e( 'Flat (dividers)', 'next-level-faq' ); ?></option>
									<option value="cards" <?php selected( $options['layout'] ?? 'flat', 'cards' ); ?>><?php esc_html_e( 'Cards (floating)', 'next-level-faq' ); ?></option>
									<option value="bordered" <?php selected( $options['layout'] ?? 'flat', 'bordered' ); ?>><?php esc_html_e( 'Bordered (stacked)', 'next-level-faq' ); ?></option>
									<option value="clean" <?php selected( $options['layout'] ?? 'flat', 'clean' ); ?>><?php esc_html_e( 'Clean (no dividers)', 'next-level-faq' ); ?></option>
									<option value="striped" <?php selected( $options['layout'] ?? 'flat', 'striped' ); ?>><?php esc_html_e( 'Striped (alternating)', 'next-level-faq' ); ?></option>
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
						data-shadow="<?php echo esc_attr( is_string( $options['shadow'] ) ? $options['shadow'] : ( $options['shadow'] ? 'md' : 'none' ) ); ?>"
						data-icon-style="<?php echo esc_attr( $options['icon_style'] ); ?>"
						data-animation="<?php echo esc_attr( $options['animation'] ); ?>"
						data-layout="<?php echo esc_attr( $options['layout'] ?? 'flat' ); ?>"
						data-preset="<?php echo esc_attr( $active_preset ); ?>"
					>
						<?php
						$preview_layout = $options['layout'] ?? 'flat';
						$preview_icon   = $options['icon_style'] ?? 'plus_minus';
						$preview_classes = array( 'nlf-faq', 'nlf-faq--preview' );
						if ( 'flat' !== $preview_layout ) {
							$preview_classes[] = 'nlf-faq--layout-' . sanitize_html_class( $preview_layout );
						}
						if ( 'chevron' === $preview_icon ) {
							$preview_classes[] = 'nlf-faq--icon-chevron';
						} elseif ( 'arrow' === $preview_icon ) {
							$preview_classes[] = 'nlf-faq--icon-arrow';
						}
						?>
						<div class="<?php echo esc_attr( implode( ' ', $preview_classes ) ); ?>">
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
							<div class="nlf-faq__item">
								<div class="nlf-faq__question">
									<span><?php esc_html_e( 'Do all layout styles work the same way?', 'next-level-faq' ); ?></span>
									<span class="nlf-faq__icon" aria-hidden="true"></span>
								</div>
								<div class="nlf-faq__answer">
									<p><?php esc_html_e( 'Each layout has its own visual personality while keeping the same interactive behavior.', 'next-level-faq' ); ?></p>
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

		$cpt_groups = self::get_cpt_group_choices();
		?>
		<div class="wrap nlf-faq-admin nlf-faq-tools">

			<!-- ── Page Header ──────────────────────────── -->
			<div class="nlf-tools-header">
				<div class="nlf-tools-header__icon-wrap">
					<span class="dashicons dashicons-admin-tools"></span>
				</div>
				<div class="nlf-tools-header__content">
					<h1><?php esc_html_e( 'Tools', 'next-level-faq' ); ?></h1>
					<p><?php esc_html_e( 'Manage, backup, and migrate your FAQ data with powerful utilities.', 'next-level-faq' ); ?></p>
				</div>
			</div>

			<?php self::output_tools_notice(); ?>

			<!-- ── Data Management ──────────────────────── -->
			<div class="nlf-tools-section">
				<div class="nlf-tools-section__header">
					<span class="dashicons dashicons-database"></span>
					<div>
						<h2><?php esc_html_e( 'Data Management', 'next-level-faq' ); ?></h2>
						<p><?php esc_html_e( 'Export and import your FAQ content, themes, and settings.', 'next-level-faq' ); ?></p>
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
								<h3><?php esc_html_e( 'Export', 'next-level-faq' ); ?></h3>
								<p><?php esc_html_e( 'Download a JSON file for backups or site migration.', 'next-level-faq' ); ?></p>
							</div>
						</div>
						<div class="nlf-tool-card__body">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nlf-tool-card__form">
								<?php wp_nonce_field( 'nlf_faq_export', 'nlf_faq_export_nonce' ); ?>
								<input type="hidden" name="action" value="nlf_faq_export" />

								<div class="nlf-tool-card__field">
									<label for="nlf-faq-export-scope" class="nlf-tool-card__field-label">
										<?php esc_html_e( 'Export scope', 'next-level-faq' ); ?>
									</label>
									<select id="nlf-faq-export-scope" name="nlf_faq_export_group" class="nlf-tool-card__select">
										<option value="all"><?php esc_html_e( 'All groups (full backup)', 'next-level-faq' ); ?></option>
										<?php foreach ( $cpt_groups as $value => $label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>">
												<?php echo esc_html( $label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div id="nlf-export-global-opts" class="nlf-tool-card__options">
									<label class="nlf-tool-card__option">
										<input type="checkbox" name="nlf_faq_include_styles" value="1" checked="checked" />
										<span><?php esc_html_e( 'Include style settings', 'next-level-faq' ); ?></span>
									</label>
									<label class="nlf-tool-card__option">
										<input type="checkbox" name="nlf_faq_include_questions" value="1" checked="checked" />
										<span><?php esc_html_e( 'Include FAQ entries', 'next-level-faq' ); ?></span>
									</label>
								</div>

								<p class="nlf-tool-card__hint" id="nlf-export-group-hint" style="display:none;">
									<?php esc_html_e( 'Exports the selected group with all its questions, theme, and settings.', 'next-level-faq' ); ?>
								</p>

								<?php submit_button( __( 'Download Export', 'next-level-faq' ), 'primary', 'submit', false ); ?>
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
								<h3><?php esc_html_e( 'Import', 'next-level-faq' ); ?></h3>
								<p><?php esc_html_e( 'Upload a JSON file to restore FAQ data from a backup.', 'next-level-faq' ); ?></p>
							</div>
						</div>
						<div class="nlf-tool-card__body">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nlf-tool-card__form" enctype="multipart/form-data">
								<?php wp_nonce_field( 'nlf_faq_import', 'nlf_faq_import_nonce' ); ?>
								<input type="hidden" name="action" value="nlf_faq_import" />

								<div class="nlf-tool-card__field">
									<label for="nlf-faq-import-target" class="nlf-tool-card__field-label">
										<?php esc_html_e( 'Import target', 'next-level-faq' ); ?>
									</label>
									<select id="nlf-faq-import-target" name="nlf_faq_import_target" class="nlf-tool-card__select">
										<option value="all"><?php esc_html_e( 'Global (all FAQ data)', 'next-level-faq' ); ?></option>
										<option value="duplicate"><?php esc_html_e( 'Duplicate as new group', 'next-level-faq' ); ?></option>
										<?php foreach ( $cpt_groups as $value => $label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>">
												<?php echo esc_html( $label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<p class="nlf-tool-card__hint" id="nlf-import-duplicate-hint" style="display:none;">
									<?php esc_html_e( 'Creates a brand-new group from the exported file with all its questions, theme, and settings.', 'next-level-faq' ); ?>
								</p>

								<div class="nlf-tool-card__field">
									<label class="nlf-tool-card__field-label">
										<?php esc_html_e( 'Upload file', 'next-level-faq' ); ?>
									</label>
									<div class="nlf-file-zone" id="nlf-file-zone">
										<div class="nlf-file-zone__icon">
											<span class="dashicons dashicons-cloud-upload"></span>
										</div>
										<p class="nlf-file-zone__text">
											<?php esc_html_e( 'Drag & drop your file here or', 'next-level-faq' ); ?>
											<span class="nlf-file-zone__browse"><?php esc_html_e( 'browse', 'next-level-faq' ); ?></span>
										</p>
										<p class="nlf-file-zone__meta"><?php esc_html_e( 'Accepts .json files only', 'next-level-faq' ); ?></p>
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
										<button type="button" class="nlf-file-info__remove" id="nlf-file-remove" title="<?php esc_attr_e( 'Remove file', 'next-level-faq' ); ?>">&times;</button>
									</div>
								</div>

								<div id="nlf-import-replace-opt" class="nlf-tool-card__options">
									<label class="nlf-tool-card__option">
										<input type="checkbox" name="nlf_faq_replace_existing" value="1" />
										<span><?php esc_html_e( 'Replace existing items before import', 'next-level-faq' ); ?></span>
									</label>
								</div>

								<div id="nlf-import-group-opts" class="nlf-tool-card__options" style="display:none;">
									<label class="nlf-tool-card__option">
										<input type="checkbox" name="nlf_import_apply_styles" value="1" />
										<span><?php esc_html_e( 'Apply imported theme and styles to this group', 'next-level-faq' ); ?></span>
									</label>
								</div>

								<?php submit_button( __( 'Import', 'next-level-faq' ), 'primary', 'submit', false ); ?>
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
						<h2><?php esc_html_e( 'More Tools', 'next-level-faq' ); ?></h2>
						<p><?php esc_html_e( 'Powerful utilities coming in future updates.', 'next-level-faq' ); ?></p>
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
									<?php esc_html_e( 'Reset', 'next-level-faq' ); ?>
									<span class="nlf-badge nlf-badge--soon"><?php esc_html_e( 'Soon', 'next-level-faq' ); ?></span>
								</h3>
								<p><?php esc_html_e( 'Selectively reset FAQ data, styles, or all plugin settings at once.', 'next-level-faq' ); ?></p>
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
									<?php esc_html_e( 'Diagnostics', 'next-level-faq' ); ?>
									<span class="nlf-badge nlf-badge--soon"><?php esc_html_e( 'Soon', 'next-level-faq' ); ?></span>
								</h3>
								<p><?php esc_html_e( 'Analyze your FAQ setup and get optimization suggestions.', 'next-level-faq' ); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>

		</div>

		<script>
		(function(){
			/* ── Export: toggle global options vs single-group hint ── */
			var expScope  = document.getElementById('nlf-faq-export-scope');
			var expGlobal = document.getElementById('nlf-export-global-opts');
			var expHint   = document.getElementById('nlf-export-group-hint');
			if(expScope){
				expScope.addEventListener('change',function(){
					var isAll = this.value === 'all';
					expGlobal.style.display = isAll ? '' : 'none';
					expHint.style.display   = isAll ? 'none' : '';
				});
			}

			/* ── Import: toggle options based on target ── */
			var impTarget    = document.getElementById('nlf-faq-import-target');
			var impGroupOps  = document.getElementById('nlf-import-group-opts');
			var impReplaceOp = document.getElementById('nlf-import-replace-opt');
			var impDupHint   = document.getElementById('nlf-import-duplicate-hint');
			if(impTarget){
				impTarget.addEventListener('change',function(){
					var v = this.value;
					var isGroup = v !== 'all' && v !== 'duplicate';
					var isDup   = v === 'duplicate';
					impGroupOps.style.display  = isGroup ? '' : 'none';
					impReplaceOp.style.display = isDup ? 'none' : '';
					impDupHint.style.display   = isDup ? '' : 'none';
				});
			}

			/* ── File upload zone UX ── */
			var zone     = document.getElementById('nlf-file-zone');
			var fileInfo = document.getElementById('nlf-file-info');
			var fileInp  = document.getElementById('nlf-faq-import-file');
			var fileName = document.getElementById('nlf-file-name');
			var fileSize = document.getElementById('nlf-file-size');
			var fileRem  = document.getElementById('nlf-file-remove');

			function formatBytes(bytes) {
				if (bytes === 0) return '0 Bytes';
				var k = 1024, sizes = ['Bytes','KB','MB'];
				var i = Math.floor(Math.log(bytes) / Math.log(k));
				return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
			}

			function showFileInfo() {
				if (fileInp.files && fileInp.files.length) {
					var f = fileInp.files[0];
					fileName.textContent = f.name;
					fileSize.textContent = formatBytes(f.size);
					zone.style.display = 'none';
					fileInfo.classList.add('is-visible');
				}
			}

			function clearFile() {
				fileInp.value = '';
				zone.style.display = '';
				fileInfo.classList.remove('is-visible');
			}

			if (fileInp) {
				fileInp.addEventListener('change', showFileInfo);
			}
			if (fileRem) {
				fileRem.addEventListener('click', clearFile);
			}

			/* Drag & drop visual feedback */
			if (zone) {
				['dragenter','dragover'].forEach(function(evt){
					zone.addEventListener(evt, function(e){
						e.preventDefault();
						zone.classList.add('is-dragover');
					});
				});
				['dragleave','drop'].forEach(function(evt){
					zone.addEventListener(evt, function(e){
						e.preventDefault();
						zone.classList.remove('is-dragover');
					});
				});
			}
		})();
		</script>
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

		$group_choice = isset( $_POST['nlf_faq_export_group'] )
			? sanitize_text_field( wp_unslash( $_POST['nlf_faq_export_group'] ) )
			: 'all';

		// ── Single-group export ──────────────────────────────
		if ( 'all' !== $group_choice && is_numeric( $group_choice ) ) {
			$group_id = absint( $group_choice );
			$payload  = self::build_group_export_payload( $group_id );

			if ( null === $payload ) {
				self::store_tools_notice( 'error', __( 'Unable to export this group. It may not exist.', 'next-level-faq' ) );
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
			self::store_tools_notice( 'error', __( 'Select at least one component to export.', 'next-level-faq' ) );
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
			$payload['meta']['group_scope_label'] = __( 'All groups', 'next-level-faq' );
			$payload['faqs']                      = self::group_faq_export_items(
				Repository::get_all_items_for_export( null )
			);
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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to import FAQs.', 'next-level-faq' ) );
		}

		check_admin_referer( 'nlf_faq_import', 'nlf_faq_import_nonce' );

		$page_url     = self::get_tools_page_url();
		$import_target = isset( $_POST['nlf_faq_import_target'] )
			? sanitize_text_field( wp_unslash( $_POST['nlf_faq_import_target'] ) )
			: 'all';

		// ── Common file validation ───────────────────────────
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

		// ── Duplicate as new group(s) ────────────────────────
		if ( 'duplicate' === $import_target ) {
			$has_single_items = ! empty( $data['items'] ) && is_array( $data['items'] );
			$has_global_faqs  = ! empty( $data['faqs'] ) && is_array( $data['faqs'] );

			if ( ! $has_single_items && ! $has_global_faqs ) {
				self::store_tools_notice( 'error', __( 'This file does not contain any FAQ data to duplicate.', 'next-level-faq' ) );
				wp_safe_redirect( $page_url );
				exit;
			}

			$groups_created = 0;
			$total_imported = 0;

			// ── Single-group export file (has 'items' key) ───
			if ( $has_single_items ) {
				$original_title = isset( $data['meta']['title'] ) ? sanitize_text_field( $data['meta']['title'] ) : '';

				$new_title = '' !== $original_title
					? sprintf( __( '%s (Copy)', 'next-level-faq' ), $original_title )
					: __( 'Imported Group (Copy)', 'next-level-faq' );

				$new_group_id = Groups_Repository::create_group(
					array(
						'title'  => $new_title,
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
				foreach ( $data['faqs'] as $original_group_id => $items ) {
					if ( ! is_array( $items ) || empty( $items ) ) {
						continue;
					}

					// Try to get the original group title.
					$source_group   = Groups_Repository::get_group_by_id( (int) $original_group_id );
					$original_title = $source_group ? trim( $source_group->title ) : '';

					$new_title = '' !== $original_title
						? sprintf( __( '%s (Copy)', 'next-level-faq' ), $original_title )
						: sprintf( __( 'Group #%d (Copy)', 'next-level-faq' ), (int) $original_group_id );

					// Build creation data, copying settings from original group if it exists.
					$create_data = array(
						'title'  => $new_title,
						'status' => 'draft',
					);

					if ( $source_group ) {
						$create_data['theme_settings']   = $source_group->theme_settings;
						$create_data['display_settings']  = $source_group->display_settings;
						$create_data['use_custom_style']  = $source_group->use_custom_style;
						$create_data['custom_styles']     = $source_group->custom_styles;
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
				self::store_tools_notice( 'error', __( 'Failed to create any new groups.', 'next-level-faq' ) );
				wp_safe_redirect( $page_url );
				exit;
			}

			$message = sprintf(
				/* translators: 1: number of groups created, 2: number of items */
				_n(
					'%1$d new group created with %2$d FAQ items. Saved as draft.',
					'%1$d new groups created with %2$d FAQ items. All saved as drafts.',
					$groups_created,
					'next-level-faq'
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
				self::store_tools_notice( 'error', __( 'The selected group does not exist.', 'next-level-faq' ) );
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

		if ( isset( $sanitized_styles ) && class_exists( 'Krslys\NextLevelFaq\Style_Generator' ) ) {
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
	 * Get FAQ group choices from the custom groups table.
	 *
	 * Returns an array of group_id => title for all groups.
	 *
	 * @return array
	 */
	private static function get_cpt_group_choices() {
		$choices = array();

		$groups = Groups_Repository::get_all_groups( null, 'title', 'ASC' );

		foreach ( $groups as $group ) {
			$title = trim( $group->title );

			$choices[ (string) $group->id ] = '' !== $title
				? $title
				: sprintf( __( 'Group #%d', 'next-level-faq' ), (int) $group->id );
		}

		return $choices;
	}
}
