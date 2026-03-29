<?php
/**
 * FAQ Group custom post type.
 *
 * @package Krslys\NextLevelFaq
 */

namespace Krslys\NextLevelFaq;
use Krslys\NextLevelFaq\Admin_UI_Components;
use Krslys\NextLevelFaq\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

/**
 * FAQ Group custom post type.
 *
 * Each post represents a group/section that will hold a repeater
 * of FAQ items stored in the custom nlf_faq_items table.
 *
 * SECURITY FEATURES:
 * - All metabox saves protected with nonce verification.
	 * - Capability checks via custom capability_type (nlf_faq_group/nlf_faq_groups).
 * - Input sanitization via sanitize_text_field() and wp_kses_post().
 * - Output escaping via esc_attr(), esc_html().
 */
class Group_CPT {

	const POST_TYPE = 'nlf_faq_group';

	/**
	 * Return the primitive capabilities introduced by this CPT.
	 *
	 * These are the plural-form caps that WordPress generates from the
	 * custom capability_type. Keeping them in one place makes granting
	 * and revoking them across files straightforward and future-proof.
	 *
	 * @return string[]
	 */
	public static function get_primitive_caps() {
		return array(
			'edit_nlf_faq_groups',
			'edit_others_nlf_faq_groups',
			'publish_nlf_faq_groups',
			'read_private_nlf_faq_groups',
			'delete_nlf_faq_groups',
			'delete_private_nlf_faq_groups',
			'delete_published_nlf_faq_groups',
			'delete_others_nlf_faq_groups',
			'edit_private_nlf_faq_groups',
			'edit_published_nlf_faq_groups',
		);
	}

	/**
	 * Grant CPT capabilities to administrator and editor roles.
	 *
	 * Called on plugin activation so that those roles can manage FAQ Groups
	 * immediately. Idempotent — safe to call on every activation/upgrade.
	 */
	public static function grant_caps() {
		$roles = array( 'administrator', 'editor' );

		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( self::get_primitive_caps() as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Revoke CPT capabilities from all roles.
	 *
	 * Called on plugin uninstall to leave the database in a clean state.
	 */
	public static function revoke_caps() {
		global $wp_roles;

		foreach ( array_keys( $wp_roles->roles ) as $role_name ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( self::get_primitive_caps() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Register CPT.
	 */
	public static function register() {
		$labels = array(
			'name'               => __( 'FAQ Groups', 'next-level-faq' ),
			'singular_name'      => __( 'FAQ Group', 'next-level-faq' ),
			'add_new'            => __( 'Add New', 'next-level-faq' ),
			'add_new_item'       => __( 'Add New FAQ Group', 'next-level-faq' ),
			'edit_item'          => __( 'Edit FAQ Group', 'next-level-faq' ),
			'new_item'           => __( 'New FAQ Group', 'next-level-faq' ),
			'all_items'          => __( 'FAQ Groups', 'next-level-faq' ),
			'view_item'          => __( 'View FAQ Group', 'next-level-faq' ),
			'search_items'       => __( 'Search FAQ Groups', 'next-level-faq' ),
			'not_found'          => __( 'No FAQ groups found', 'next-level-faq' ),
			'not_found_in_trash' => __( 'No FAQ groups found in Trash', 'next-level-faq' ),
			'menu_name'          => __( 'FAQ Groups', 'next-level-faq' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'rest_base'          => 'nlf-faq-groups',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'show_in_nav_menus'  => false,
			'show_in_admin_bar'  => false,
			'show_in_menu'       => 'nlf-faq',
			'supports'           => array( 'title' ),
			'capability_type'    => array( 'nlf_faq_group', 'nlf_faq_groups' ),
			'map_meta_cap'       => true,
			'has_archive'        => false,
		);

		register_post_type( self::POST_TYPE, $args );

		add_action( 'add_meta_boxes', array( __CLASS__, 'register_metaboxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_metabox' ), 10, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_nlf_get_group_preview', array( __CLASS__, 'ajax_get_group_preview' ) );
		add_filter( 'redirect_post_location', array( __CLASS__, 'append_save_notice_flag' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'render_save_notice' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'add_row_actions' ), 10, 2 );
		add_action( 'admin_post_nlf_duplicate_group', array( __CLASS__, 'handle_duplicate_group' ) );

		// List table columns.
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'register_list_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_list_column' ), 10, 2 );
	}

	/**
	 * Register metaboxes.
	 */
	public static function register_metaboxes() {
		add_meta_box(
			'nlf_faq_group_tabs',
			__( 'FAQ Group Configuration', 'next-level-faq' ),
			array( __CLASS__, 'render_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'nlf_faq_how_to_use',
			__( 'How To Use', 'next-level-faq' ),
			array( __CLASS__, 'render_how_to_use_metabox' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the "How To Use" side metabox.
	 *
	 * Shows shortcode, PHP template tag, and Gutenberg block usage
	 * with click-to-copy interaction. Always displays the post ID.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function render_how_to_use_metabox( $post ) {
		$post_id        = (int) $post->ID;
		$shortcode_text = '[krslys_nlf group="' . $post_id . '"]';
		$php_text       = "<?php echo do_shortcode( '" . $shortcode_text . "' ); ?>";

		// Inline SVG icons — no dashicon rendering issues.
		$icon_copy = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
		$icon_check = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
		$icon_block = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>';
		?>
		<div class="nlf-how-to-use">

			<button type="button" class="nlf-htu-snippet" data-copy-text="<?php echo esc_attr( $shortcode_text ); ?>">
				<span class="nlf-htu-snippet__label"><?php esc_html_e( 'Shortcode', 'next-level-faq' ); ?></span>
				<span class="nlf-htu-snippet__row">
					<code class="nlf-htu-snippet__code"><?php echo esc_html( $shortcode_text ); ?></code>
					<span class="nlf-htu-snippet__icon nlf-htu-snippet__icon--copy"><?php echo $icon_copy; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
					<span class="nlf-htu-snippet__icon nlf-htu-snippet__icon--ok"><?php echo $icon_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
				</span>
				<span class="nlf-htu-snippet__toast"><?php esc_html_e( 'Copied!', 'next-level-faq' ); ?></span>
			</button>

			<button type="button" class="nlf-htu-snippet" data-copy-text="<?php echo esc_attr( $php_text ); ?>">
				<span class="nlf-htu-snippet__label"><?php esc_html_e( 'PHP Template', 'next-level-faq' ); ?></span>
				<span class="nlf-htu-snippet__row">
					<code class="nlf-htu-snippet__code"><?php echo esc_html( $php_text ); ?></code>
					<span class="nlf-htu-snippet__icon nlf-htu-snippet__icon--copy"><?php echo $icon_copy; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
					<span class="nlf-htu-snippet__icon nlf-htu-snippet__icon--ok"><?php echo $icon_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
				</span>
				<span class="nlf-htu-snippet__toast"><?php esc_html_e( 'Copied!', 'next-level-faq' ); ?></span>
			</button>

			<div class="nlf-htu-block-hint">
				<span class="nlf-htu-block-hint__icon"><?php echo $icon_block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
				<span class="nlf-htu-block-hint__text">
					<?php esc_html_e( 'Also available as a Gutenberg block.', 'next-level-faq' ); ?>
				</span>
			</div>

		</div>
		<?php
	}

	/**
	 * Enqueue admin assets for group editor.
	 *
	 * SECURITY: Validates screen object and post type.
	 *
	 * @param string $hook_suffix Hook suffix.
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		$screen = get_current_screen();

		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		// List table page: enqueue admin CSS + clipboard script.
		if ( 'edit.php' === $hook_suffix ) {
			wp_enqueue_style(
				'nlf-faq-admin',
				nlf_asset_url( 'assets/css/admin-faq-style.css' ),
				array(),
				NLF_FAQ_VERSION
			);

			// Inline script for shortcode copy-to-clipboard.
			wp_add_inline_script( 'common', '
				document.addEventListener("click",function(e){
					var btn=e.target.closest(".nlf-list-shortcode");
					if(!btn) return;
					e.preventDefault();
					var text=btn.getAttribute("data-clipboard");
					if(!text) return;
					if(navigator.clipboard){
						navigator.clipboard.writeText(text);
					}else{
						var t=document.createElement("textarea");
						t.value=text;t.style.position="fixed";t.style.opacity="0";
						document.body.appendChild(t);t.select();
						document.execCommand("copy");document.body.removeChild(t);
					}
					btn.classList.add("is-copied");
					setTimeout(function(){btn.classList.remove("is-copied");},1500);
				});
			' );

			return;
		}

		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		// Ensure WordPress editor (TinyMCE/Quicktags) assets are available for WYSIWYG answers.
		if ( function_exists( 'wp_enqueue_editor' ) ) {
			wp_enqueue_editor();
		}

		// Enqueue color picker.
		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_style(
			'nlf-faq-admin',
			nlf_asset_url( 'assets/css/admin-faq-style.css' ),
			array( 'wp-color-picker' ),
			NLF_FAQ_VERSION
		);

		// Enqueue generated FAQ styles for preview.
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

		// Enqueue frontend FAQ script for preview toggle functionality.
		wp_enqueue_script(
			'nlf-faq-frontend',
			nlf_asset_url( 'assets/js/frontend-faq.js' ),
			array(),
			NLF_FAQ_VERSION,
			true
		);

		wp_enqueue_script(
			'nlf-faq-group-metabox',
			nlf_asset_url( 'assets/js/admin-faq-group-metabox.js' ),
			array( 'wp-editor', 'wp-color-picker', 'nlf-faq-frontend' ),
			NLF_FAQ_VERSION,
			true
		);

		// Localize script for AJAX.
		wp_localize_script(
			'nlf-faq-group-metabox',
			'nlfGroupData',
			array(
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'nlf_group_preview' ),
				'saveNonce' => wp_create_nonce( 'nlf_faq_group_save' ),
				'postId'    => get_the_ID(),
				'i18n'      => array(
					'saving' => __( 'Saving…', 'next-level-faq' ),
					'saved'  => __( 'Saved!', 'next-level-faq' ),
				),
			)
		);
	}

	/**
	 * Render tabbed metabox for FAQ Group.
	 *
	 * SECURITY: All output properly escaped.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function render_metabox( $post ) {
		wp_nonce_field( 'nlf_faq_group_save', 'nlf_faq_group_nonce' );

		// Get saved data
		$items              = Repository::get_items_for_group( $post->ID, false );
		$current_theme      = get_post_meta( $post->ID, '_nlf_faq_group_theme', true );
		$theme_custom       = get_post_meta( $post->ID, '_nlf_faq_group_theme_custom', true );
		$settings           = get_post_meta( $post->ID, '_nlf_faq_group_settings', true );
		$use_custom_style   = get_post_meta( $post->ID, '_nlf_faq_group_use_custom_style', true );
		$custom_styles      = get_post_meta( $post->ID, '_nlf_faq_group_custom_styles', true );

		// Set defaults
		if ( empty( $current_theme ) ) {
			$current_theme = 'default';
		}
		if ( ! is_array( $theme_custom ) ) {
			$theme_custom = array();
		}
		if ( ! is_array( $settings ) ) {
			$settings = self::get_default_settings();
		}
		if ( ! is_array( $custom_styles ) ) {
			$custom_styles = Options::get_defaults();
		}
		?>

		<div class="nlf-faq-group-tabs-wrapper">
			<!-- Tab Navigation with ARIA -->
			<div class="nlf-faq-tabs-nav" role="tablist" aria-label="<?php esc_attr_e( 'FAQ Group Configuration', 'next-level-faq' ); ?>">
				<button 
					type="button" 
					role="tab"
					class="nlf-faq-tab-button active" 
					data-tab="content"
					id="tab-content"
					aria-selected="true"
					aria-controls="panel-content">
					<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
					<span class="nlf-tab-label"><?php esc_html_e( 'Content', 'next-level-faq' ); ?></span>
				</button>
				<button 
					type="button"
					role="tab" 
					class="nlf-faq-tab-button" 
					data-tab="appearance"
					id="tab-appearance"
					aria-selected="false"
					aria-controls="panel-appearance">
					<span class="dashicons dashicons-art" aria-hidden="true"></span>
					<span class="nlf-tab-label"><?php esc_html_e( 'Appearance', 'next-level-faq' ); ?></span>
				</button>
				<button 
					type="button"
					role="tab" 
					class="nlf-faq-tab-button" 
					data-tab="preview"
					id="tab-preview"
					aria-selected="false"
					aria-controls="panel-preview">
					<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					<span class="nlf-tab-label"><?php esc_html_e( 'Preview', 'next-level-faq' ); ?></span>
				</button>
			</div>

			<!-- Mobile Preview Notice (outside tabs for visibility) -->
			<?php Admin_UI_Components::mobile_preview_notice(); ?>

			<!-- Tab Panels -->
			<div class="nlf-faq-tabs-content">
				<!-- Content Tab (FAQ Items + Settings) -->
				<div 
					class="nlf-faq-tab-panel active" 
					data-tab="content"
					id="panel-content"
					role="tabpanel"
					aria-labelledby="tab-content"
					tabindex="0">
					<?php self::render_content_tab( $post, $items, $settings ); ?>
				</div>

				<!-- Appearance Tab (Themes + Style) -->
				<div 
					class="nlf-faq-tab-panel" 
					data-tab="appearance"
					id="panel-appearance"
					role="tabpanel"
					aria-labelledby="tab-appearance"
					tabindex="0"
					hidden>
					<?php self::render_appearance_tab( $post->ID, $items, $current_theme, $theme_custom, $use_custom_style, $custom_styles ); ?>
				</div>

				<!-- Preview Tab -->
				<div 
					class="nlf-faq-tab-panel" 
					data-tab="preview"
					id="panel-preview"
					role="tabpanel"
					aria-labelledby="tab-preview"
					tabindex="0"
					hidden>
					<?php self::render_preview_tab( $post->ID, $items ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Content tab (FAQ Items + Settings).
	 *
	 * @param array $items    FAQ items.
	 * @param array $settings Group settings.
	 */
	private static function render_content_tab( $post, $items, $settings ) {
		?>
		<?php if ( empty( $items ) ) : ?>
			<?php Admin_UI_Components::onboarding_card(); ?>
		<?php endif; ?>

		<!-- FAQ Items Section -->
		<div class="nlf-section">
			<div class="nlf-section-header">
				<h3><?php esc_html_e( 'FAQ Items', 'next-level-faq' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Add questions and answers that your visitors commonly ask.', 'next-level-faq' ); ?>
				</p>
			</div>
			<?php self::render_faq_items_table( $items ); ?>
		</div>

		<!-- Settings Section -->
		<div class="nlf-section nlf-section-bordered">
			<div class="nlf-section-header">
				<h3><?php esc_html_e( 'Behavior & Display Settings', 'next-level-faq' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Control how users interact with your FAQs.', 'next-level-faq' ); ?>
				</p>
			</div>
			<?php self::render_settings_fields( $settings ); ?>
		</div>

		<?php
	}

	/**
	 * Render FAQ Items table.
	 *
	 * @param array $items FAQ items.
	 */
	private static function render_faq_items_table( $items ) {
		if ( empty( $items ) ) {
			// Empty state
			Admin_UI_Components::empty_state(
				array(
					'title'       => __( 'No questions yet', 'next-level-faq' ),
					'description' => __( 'Add questions and answers that your visitors commonly ask.', 'next-level-faq' ),
					'primary'     => array(
						'label' => __( 'Add Your First Question', 'next-level-faq' ),
						'id'    => 'nlf-faq-group-add-row-empty',
						'data'  => array(
							'add-row' => 'true',
						),
					),
				)
			);
		}
		?>

		<table class="widefat fixed striped nlf-faq-questions-table nlf-faq-group-table"<?php if ( empty( $items ) ) : ?> style="display:none;"<?php endif; ?>>
			<thead>
				<tr>
					<th style="width:32px;"></th>
					<th><?php esc_html_e( 'Question & Answer', 'next-level-faq' ); ?></th>
					<th style="width:200px;"><?php esc_html_e( 'Options', 'next-level-faq' ); ?></th>
				</tr>
			</thead>
			<tbody id="nlf-faq-group-questions-body">
				<?php if ( ! empty( $items ) ) : ?>
					<?php foreach ( $items as $index => $item ) : ?>
						<tr class="nlf-faq-question-row">
							<td class="nlf-faq-sort-handle">⋮⋮</td>
							<td class="nlf-faq-content-cell">
								<input type="hidden" name="nlf_faq_group_item_id[]" value="<?php echo esc_attr( $item->id ); ?>" />
								<div class="nlf-faq-question-field">
									<label class="nlf-faq-field-label"><?php esc_html_e( 'Question', 'next-level-faq' ); ?></label>
									<input type="text" class="regular-text" name="nlf_faq_group_question[]" value="<?php echo esc_attr( $item->question ); ?>" placeholder="<?php esc_attr_e( 'Enter your question...', 'next-level-faq' ); ?>" />
								</div>
								<div class="nlf-faq-answer-field">
									<label class="nlf-faq-field-label"><?php esc_html_e( 'Answer', 'next-level-faq' ); ?></label>
									<?php
									$editor_id = 'nlf_faq_group_answer_' . $index;
									wp_editor(
										$item->answer,
										$editor_id,
										array(
											'textarea_name' => 'nlf_faq_group_answer[]',
											'media_buttons' => false,
											'teeny'         => true,
											'textarea_rows' => 4,
										)
									);
									?>
								</div>
								<button type="button" class="nlf-faq-remove-row" aria-label="<?php esc_attr_e( 'Remove', 'next-level-faq' ); ?>" title="<?php esc_attr_e( 'Remove', 'next-level-faq' ); ?>">
									<span class="nlf-faq-remove-icon">×</span>
								</button>
							</td>
							<td class="nlf-faq-options-cell">
								<div class="nlf-faq-options-group">
									<p>
										<label>
											<input type="checkbox" name="nlf_faq_group_open[<?php echo esc_attr( $index ); ?>]" value="1" <?php checked( (int) $item->initial_state, 1 ); ?> />
											<?php esc_html_e( 'Open by default', 'next-level-faq' ); ?>
										</label>
									</p>
									<p>
										<label>
											<input type="checkbox" name="nlf_faq_group_visible[<?php echo esc_attr( $index ); ?>]" value="1" <?php checked( (int) $item->status, 1 ); ?> />
											<?php esc_html_e( 'Show', 'next-level-faq' ); ?>
										</label>
									</p>
									<p>
										<label>
											<input type="checkbox" name="nlf_faq_group_highlight[<?php echo esc_attr( $index ); ?>]" value="1" <?php checked( (int) $item->highlight, 1 ); ?> />
											<?php esc_html_e( 'Highlight', 'next-level-faq' ); ?>
										</label>
									</p>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="3">
						<button type="button" class="button button-secondary nlf-faq-group-add-row-btn" id="nlf-faq-group-add-row-footer">
							<?php esc_html_e( 'Add Question', 'next-level-faq' ); ?>
						</button>
					</td>
				</tr>
			</tfoot>
		</table>

		<script type="text/template" id="tmpl-nlf-faq-group-row">
			<tr class="nlf-faq-question-row">
				<td class="nlf-faq-sort-handle">⋮⋮</td>
				<td class="nlf-faq-content-cell">
					<input type="hidden" name="nlf_faq_group_item_id[]" value="" />
					<div class="nlf-faq-question-field">
						<label class="nlf-faq-field-label"><?php esc_html_e( 'Question', 'next-level-faq' ); ?></label>
						<input type="text" class="regular-text" name="nlf_faq_group_question[]" value="" placeholder="<?php esc_attr_e( 'Enter your question...', 'next-level-faq' ); ?>" />
					</div>
					<div class="nlf-faq-answer-field">
						<label class="nlf-faq-field-label"><?php esc_html_e( 'Answer', 'next-level-faq' ); ?></label>
						<textarea id="nlf-faq-group-answer-{{index}}" name="nlf_faq_group_answer[]" rows="4" class="large-text nlf-faq-group-answer-editor" placeholder="<?php esc_attr_e( 'Enter your answer...', 'next-level-faq' ); ?>"></textarea>
					</div>
					<button type="button" class="nlf-faq-remove-row" aria-label="<?php esc_attr_e( 'Remove', 'next-level-faq' ); ?>" title="<?php esc_attr_e( 'Remove', 'next-level-faq' ); ?>">
						<span class="nlf-faq-remove-icon">×</span>
					</button>
				</td>
				<td class="nlf-faq-options-cell">
					<div class="nlf-faq-options-group">
						<p>
							<label>
								<input type="checkbox" name="nlf_faq_group_open[{{index}}]" value="1" checked="checked" />
								<?php esc_html_e( 'Open by default', 'next-level-faq' ); ?>
							</label>
						</p>
						<p>
							<label>
								<input type="checkbox" name="nlf_faq_group_visible[{{index}}]" value="1" checked="checked" />
								<?php esc_html_e( 'Show', 'next-level-faq' ); ?>
							</label>
						</p>
						<p>
							<label>
								<input type="checkbox" name="nlf_faq_group_highlight[{{index}}]" value="1" />
								<?php esc_html_e( 'Highlight', 'next-level-faq' ); ?>
							</label>
						</p>
					</div>
				</td>
			</tr>
		</script>
		<?php
	}

	/**
	 * Render Appearance tab (Themes + Style consolidated).
	 *
	 * @param int    $post_id          Post ID.
	 * @param array  $items            FAQ items.
	 * @param string $current_theme    Selected theme.
	 * @param array  $theme_custom     Custom theme colors.
	 * @param bool   $use_custom_style Whether to use custom styles.
	 * @param array  $custom_styles    Custom style values.
	 */
	private static function render_appearance_tab( $post_id, $items, $current_theme, $theme_custom, $use_custom_style, $custom_styles ) {
		?>
		<div class="nlf-appearance-wrapper">
			<div class="nlf-appearance-layout">
				<div class="nlf-appearance-controls">
					<!-- Quick Style Section -->
					<div class="nlf-section">
						<div class="nlf-section-header">
							<h3><?php esc_html_e( 'Theme Presets', 'next-level-faq' ); ?></h3>
							<p class="description">
								<?php esc_html_e( 'Choose a pre-designed theme to quickly style your FAQs.', 'next-level-faq' ); ?>
							</p>
						</div>
						<?php self::render_theme_selector( $current_theme, $theme_custom ); ?>
					</div>

					<!-- Advanced Styles Section -->
					<div class="nlf-section nlf-section-bordered">
						<div class="nlf-section-header">
							<h3><?php esc_html_e( 'Advanced Style Overrides', 'next-level-faq' ); ?></h3>
							<p class="description">
								<?php esc_html_e( 'Fine-tune every detail or override global styles for this group.', 'next-level-faq' ); ?>
							</p>
						</div>
						<?php self::render_custom_styles( $use_custom_style, $custom_styles ); ?>
					</div>

					<div class="nlf-reset-row">
						<button type="button" class="button button-secondary" data-reset="theme">
							<?php esc_html_e( 'Reset Theme', 'next-level-faq' ); ?>
						</button>
						<button type="button" class="button button-secondary" data-reset="styles">
							<?php esc_html_e( 'Reset Styles', 'next-level-faq' ); ?>
						</button>
					</div>
				</div>

				<div class="nlf-appearance-preview">
					<div class="nlf-preview-mini-header">
						<h3><?php esc_html_e( 'Live Preview', 'next-level-faq' ); ?></h3>
						<div class="nlf-preview-mini-actions">
							<label class="nlf-preview-auto nlf-preview-auto--small">
								<input type="checkbox" class="nlf-preview-auto-toggle" data-preview-auto="appearance" checked>
								<span><?php esc_html_e( 'Auto refresh', 'next-level-faq' ); ?></span>
							</label>
							<button type="button" class="button button-small" data-refresh-preview="appearance">
								<span class="dashicons dashicons-update" aria-hidden="true"></span>
								<?php esc_html_e( 'Refresh', 'next-level-faq' ); ?>
							</button>
						</div>
					</div>
					<p class="description"><?php esc_html_e( 'Changes update instantly as you tweak styles.', 'next-level-faq' ); ?></p>
					<?php if ( empty( $items ) ) : ?>
						<div class="nlf-preview-empty-state nlf-preview-empty-state--mini">
							<p><?php esc_html_e( 'Add at least one question to see the live preview.', 'next-level-faq' ); ?></p>
							<button type="button" class="button button-secondary" data-switch-tab="content">
								<?php esc_html_e( 'Add questions first', 'next-level-faq' ); ?> →
							</button>
						</div>
					<?php else : ?>
						<?php Admin_UI_Components::preview_container( $post_id, 'appearance' ); ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render theme selector.
	 *
	 * @param string $current_theme Selected theme.
	 * @param array  $theme_custom  Custom theme colors.
	 */
	private static function render_theme_selector( $current_theme, $theme_custom ) {
		$themes = self::get_theme_presets();

		$layout_labels = array(
			'flat'     => __( 'Flat', 'next-level-faq' ),
			'cards'    => __( 'Cards', 'next-level-faq' ),
			'bordered' => __( 'Bordered', 'next-level-faq' ),
			'clean'    => __( 'Clean', 'next-level-faq' ),
			'striped'  => __( 'Striped', 'next-level-faq' ),
		);
		?>
		<div class="nlf-theme-selector" role="radiogroup" aria-label="<?php esc_attr_e( 'Choose a theme preset', 'next-level-faq' ); ?>" data-default-theme="default">
			<?php foreach ( $themes as $theme_id => $theme_data ) :
				$layout = $theme_data['values']['layout'] ?? 'flat';
				$is_cards = 'cards' === $layout;
				$radius = ( $theme_data['values']['container_border_radius'] ?? 8 ) . 'px';
				$has_shadow = ! empty( $theme_data['values']['shadow'] ) && false !== $theme_data['values']['shadow'];
				$preview_shadow = $has_shadow ? '0 2px 8px rgba(0,0,0,0.08)' : 'none';
			?>
				<div class="nlf-theme-option <?php echo ( $current_theme === $theme_id ) ? 'is-active' : ''; ?>" data-theme="<?php echo esc_attr( $theme_id ); ?>">
					<input type="radio" name="nlf_faq_group_theme" value="<?php echo esc_attr( $theme_id ); ?>" <?php checked( $current_theme, $theme_id ); ?> id="theme_<?php echo esc_attr( $theme_id ); ?>" />
					<label for="theme_<?php echo esc_attr( $theme_id ); ?>">
						<div class="nlf-theme-preview nlf-theme-preview--<?php echo esc_attr( $layout ); ?>" style="
							background: <?php echo $is_cards ? 'transparent' : esc_attr( $theme_data['background'] ); ?>;
							border-color: <?php echo $is_cards ? 'transparent' : esc_attr( $theme_data['border'] ); ?>;
							border-radius: <?php echo esc_attr( $radius ); ?>;
							box-shadow: <?php echo $is_cards ? 'none' : esc_attr( $preview_shadow ); ?>;
						">
							<div class="nlf-theme-preview-item" style="
								background: <?php echo $is_cards ? esc_attr( $theme_data['background'] ) : 'transparent'; ?>;
								border: <?php echo $is_cards ? '1px solid ' . esc_attr( $theme_data['border'] ) : 'none'; ?>;
								border-radius: <?php echo $is_cards ? esc_attr( $radius ) : '0'; ?>;
								border-bottom: <?php echo ! $is_cards ? '1px solid ' . esc_attr( $theme_data['border'] ) : 'none'; ?>;
								box-shadow: <?php echo $is_cards ? esc_attr( $preview_shadow ) : 'none'; ?>;
								padding: 8px <?php echo $is_cards ? '10px' : '0'; ?>;
							">
								<div class="nlf-theme-preview-question" style="color: <?php echo esc_attr( $theme_data['question'] ); ?>;">
									<?php esc_html_e( 'Sample Question?', 'next-level-faq' ); ?>
								</div>
								<div class="nlf-theme-preview-answer" style="color: <?php echo esc_attr( $theme_data['answer'] ); ?>;">
									<?php esc_html_e( 'Preview answer text...', 'next-level-faq' ); ?>
								</div>
							</div>
							<div class="nlf-theme-preview-item nlf-theme-preview-item--collapsed" style="
								background: <?php echo $is_cards ? esc_attr( $theme_data['background'] ) : 'transparent'; ?>;
								border: <?php echo $is_cards ? '1px solid ' . esc_attr( $theme_data['border'] ) : 'none'; ?>;
								border-radius: <?php echo $is_cards ? esc_attr( $radius ) : '0'; ?>;
								box-shadow: <?php echo $is_cards ? '0 1px 3px rgba(0,0,0,0.04)' : 'none'; ?>;
								padding: 8px <?php echo $is_cards ? '10px' : '0'; ?>;
							">
								<div class="nlf-theme-preview-question" style="color: <?php echo esc_attr( $theme_data['question'] ); ?>; opacity: 0.7;">
									<?php esc_html_e( 'Another Question?', 'next-level-faq' ); ?>
								</div>
							</div>
							<div class="nlf-theme-preview-accent" style="background: <?php echo esc_attr( $theme_data['accent'] ); ?>;"></div>
						</div>
						<div class="nlf-theme-info">
							<div class="nlf-theme-name"><?php echo esc_html( $theme_data['name'] ); ?></div>
							<p><?php echo esc_html( $theme_data['description'] ); ?></p>
							<span class="nlf-theme-layout-tag"><?php echo esc_html( $layout_labels[ $layout ] ?? $layout ); ?></span>
						</div>
						<span class="nlf-theme-badge" aria-hidden="<?php echo ( $current_theme === $theme_id ) ? 'false' : 'true'; ?>">
							<?php esc_html_e( 'Applied', 'next-level-faq' ); ?>
						</span>
					</label>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="nlf-theme-customizer" aria-live="polite">
			<h4><?php esc_html_e( 'Customize Colors', 'next-level-faq' ); ?></h4>
			<p class="description">
				<?php esc_html_e( 'Override theme colors with your own custom values.', 'next-level-faq' ); ?>
			</p>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="theme_custom_primary"><?php esc_html_e( 'Primary Color', 'next-level-faq' ); ?></label>
					</th>
					<td>
						<input type="text" id="theme_custom_primary" name="nlf_faq_group_theme_custom[primary]" value="<?php echo esc_attr( $theme_custom['primary'] ?? '' ); ?>" class="nlf-color-picker nlf-theme-color" data-color-key="primary" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="theme_custom_secondary"><?php esc_html_e( 'Secondary Color', 'next-level-faq' ); ?></label>
					</th>
					<td>
						<input type="text" id="theme_custom_secondary" name="nlf_faq_group_theme_custom[secondary]" value="<?php echo esc_attr( $theme_custom['secondary'] ?? '' ); ?>" class="nlf-color-picker nlf-theme-color" data-color-key="secondary" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="theme_custom_accent"><?php esc_html_e( 'Accent Color', 'next-level-faq' ); ?></label>
					</th>
					<td>
						<input type="text" id="theme_custom_accent" name="nlf_faq_group_theme_custom[accent]" value="<?php echo esc_attr( $theme_custom['accent'] ?? '' ); ?>" class="nlf-color-picker nlf-theme-color" data-color-key="accent" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="theme_custom_background"><?php esc_html_e( 'Background Color', 'next-level-faq' ); ?></label>
					</th>
					<td>
						<input type="text" id="theme_custom_background" name="nlf_faq_group_theme_custom[background]" value="<?php echo esc_attr( $theme_custom['background'] ?? '' ); ?>" class="nlf-color-picker nlf-theme-color" data-color-key="background" />
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Render Settings fields.
	 *
	 * @param array $settings Group settings.
	 */
	private static function render_settings_fields( $settings ) {
		?>
		<div class="nlf-settings-wrapper">
			<h4 class="nlf-subsection-title"><?php esc_html_e( 'How should users interact?', 'next-level-faq' ); ?></h4>
			<table class="form-table nlf-settings-table">
				<tr>
					<th scope="row">
						<label for="setting_accordion_mode">
							<?php esc_html_e( 'Accordion Mode', 'next-level-faq' ); ?>
							<button type="button" class="nlf-help-trigger" aria-label="<?php esc_attr_e( 'Learn more', 'next-level-faq' ); ?>" data-tooltip="accordion-help">
								<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
							</button>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="setting_accordion_mode" name="nlf_faq_group_settings[accordion_mode]" value="1" <?php checked( ! empty( $settings['accordion_mode'] ) ); ?> />
							<?php esc_html_e( 'Only allow one item to be open at a time', 'next-level-faq' ); ?>
						</label>
					<p class="nlf-help-text" id="accordion-help" hidden>
							<?php esc_html_e( 'When enabled, opening one item automatically closes all others. Perfect for keeping your FAQ section compact.', 'next-level-faq' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="setting_initial_state">
							<?php esc_html_e( 'Initial State', 'next-level-faq' ); ?>
							<button type="button" class="nlf-help-trigger" aria-label="<?php esc_attr_e( 'Learn more', 'next-level-faq' ); ?>" data-tooltip="initial-help">
								<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
							</button>
						</label>
					</th>
					<td>
						<select id="setting_initial_state" name="nlf_faq_group_settings[initial_state]">
							<option value="all_closed" <?php selected( $settings['initial_state'] ?? 'all_closed', 'all_closed' ); ?>><?php esc_html_e( 'All Closed', 'next-level-faq' ); ?></option>
							<option value="first_open" <?php selected( $settings['initial_state'] ?? '', 'first_open' ); ?>><?php esc_html_e( 'First Item Open', 'next-level-faq' ); ?></option>
							<option value="custom" <?php selected( $settings['initial_state'] ?? '', 'custom' ); ?>><?php esc_html_e( 'Custom (Use item settings)', 'next-level-faq' ); ?></option>
						</select>
					<p class="nlf-help-text" id="initial-help" hidden>
							<?php esc_html_e( 'Choose how items should appear when the page loads. "Custom" uses the "Open by default" setting for each item.', 'next-level-faq' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="setting_animation_speed">
							<?php esc_html_e( 'Animation Speed', 'next-level-faq' ); ?>
							<button type="button" class="nlf-help-trigger" aria-label="<?php esc_attr_e( 'Learn more', 'next-level-faq' ); ?>" data-tooltip="animation-help">
								<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
							</button>
						</label>
					</th>
					<td>
						<select id="setting_animation_speed" name="nlf_faq_group_settings[animation_speed]">
							<option value="fast" <?php selected( $settings['animation_speed'] ?? 'normal', 'fast' ); ?>><?php esc_html_e( 'Fast (150ms)', 'next-level-faq' ); ?></option>
							<option value="normal" <?php selected( $settings['animation_speed'] ?? 'normal', 'normal' ); ?>><?php esc_html_e( 'Normal (300ms)', 'next-level-faq' ); ?></option>
							<option value="slow" <?php selected( $settings['animation_speed'] ?? '', 'slow' ); ?>><?php esc_html_e( 'Slow (500ms)', 'next-level-faq' ); ?></option>
						</select>
					<p class="nlf-help-text" id="animation-help" hidden>
							<?php esc_html_e( 'Controls how quickly items expand and collapse. Normal works well for most sites.', 'next-level-faq' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h4 class="nlf-subsection-title"><?php esc_html_e( 'What should users see?', 'next-level-faq' ); ?></h4>
			<table class="form-table nlf-settings-table">
				<tr>
					<th scope="row">
						<label for="setting_show_search">
							<?php esc_html_e( 'Search Box', 'next-level-faq' ); ?>
							<button type="button" class="nlf-help-trigger" aria-label="<?php esc_attr_e( 'Learn more', 'next-level-faq' ); ?>" data-tooltip="search-help">
								<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
							</button>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="setting_show_search" name="nlf_faq_group_settings[show_search]" value="1" <?php checked( ! empty( $settings['show_search'] ) ); ?> />
							<?php esc_html_e( 'Show search box above FAQ items', 'next-level-faq' ); ?>
						</label>
					<p class="nlf-help-text" id="search-help" hidden>
							<?php esc_html_e( 'Adds a live search box that filters questions and answers as visitors type.', 'next-level-faq' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="setting_show_counter">
							<?php esc_html_e( 'Item Counter', 'next-level-faq' ); ?>
							<button type="button" class="nlf-help-trigger" aria-label="<?php esc_attr_e( 'Learn more', 'next-level-faq' ); ?>" data-tooltip="counter-help">
								<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
							</button>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="setting_show_counter" name="nlf_faq_group_settings[show_counter]" value="1" <?php checked( ! empty( $settings['show_counter'] ) ); ?> />
							<?php esc_html_e( 'Display item numbers (e.g., 1., 2., 3.)', 'next-level-faq' ); ?>
						</label>
					<p class="nlf-help-text" id="counter-help" hidden>
							<?php esc_html_e( 'Shows numbered labels before each question for easy reference.', 'next-level-faq' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="setting_smooth_scroll">
							<?php esc_html_e( 'Smooth Scroll', 'next-level-faq' ); ?>
							<button type="button" class="nlf-help-trigger" aria-label="<?php esc_attr_e( 'Learn more', 'next-level-faq' ); ?>" data-tooltip="scroll-help">
								<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
							</button>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="setting_smooth_scroll" name="nlf_faq_group_settings[smooth_scroll]" value="1" <?php checked( ! empty( $settings['smooth_scroll'] ) ); ?> />
							<?php esc_html_e( 'Scroll to item when opened via URL hash', 'next-level-faq' ); ?>
						</label>
					<p class="nlf-help-text" id="scroll-help" hidden>
							<?php esc_html_e( 'Smoothly scrolls opened items into view, helpful when linking directly to specific questions.', 'next-level-faq' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Add duplicate link to row actions.
	 *
	 * @param array   $actions Actions list.
	 * @param \WP_Post $post   Post object.
	 *
	 * @return array
	 */
	public static function add_row_actions( $actions, $post ) {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'nlf_duplicate_group',
					'post'   => (int) $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'nlf_duplicate_group_' . $post->ID
		);

		$actions['nlf_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate', 'next-level-faq' ) . '</a>';

		return $actions;
	}

	/* ─────────────────────────────────────────────
	 * List table columns.
	 * ───────────────────────────────────────────── */

	/**
	 * Register custom columns for the FAQ Groups list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function register_list_columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			// Insert custom columns right after "title".
			if ( 'title' === $key ) {
				$new['nlf_shortcode'] = __( 'Shortcode', 'next-level-faq' );
				$new['nlf_questions'] = __( 'Questions', 'next-level-faq' );
				$new['nlf_theme']     = __( 'Theme', 'next-level-faq' );
			}
		}

		return $new;
	}

	/**
	 * Render custom column content for the FAQ Groups list table.
	 *
	 * @param string $column  Column slug.
	 * @param int    $post_id Post ID.
	 */
	public static function render_list_column( $column, $post_id ) {
		switch ( $column ) {
			case 'nlf_shortcode':
				$shortcode = '[next_level_faq id="' . (int) $post_id . '"]';
				printf(
					'<button type="button" class="nlf-list-shortcode" data-clipboard="%1$s" title="%2$s">'
					. '<code>%1$s</code>'
					. '<span class="nlf-list-shortcode__icon dashicons dashicons-clipboard"></span>'
					. '<span class="nlf-list-shortcode__ok dashicons dashicons-yes-alt"></span>'
					. '</button>',
					esc_attr( $shortcode ),
					esc_attr__( 'Copy shortcode', 'next-level-faq' )
				);
				break;

			case 'nlf_questions':
				$items = Repository::get_items_for_group( $post_id, false );
				$total = count( $items );
				$visible = 0;
				foreach ( $items as $item ) {
					if ( ! empty( $item->status ) ) {
						++$visible;
					}
				}
				$hidden = $total - $visible;

				printf(
					'<span class="nlf-list-count">'
					. '<span class="nlf-list-count__number">%d</span>'
					. '</span>',
					(int) $total
				);

				if ( $hidden > 0 ) {
					printf(
						'<span class="nlf-list-count__hidden" title="%s">(%d %s)</span>',
						esc_attr__( 'Hidden from frontend', 'next-level-faq' ),
						(int) $hidden,
						esc_html__( 'hidden', 'next-level-faq' )
					);
				}
				break;

			case 'nlf_theme':
				$theme_slug = get_post_meta( $post_id, '_nlf_faq_group_theme', true );

				if ( empty( $theme_slug ) ) {
					$theme_slug = Presets::DEFAULT_PRESET;
				}

				$registry = Presets::get_registry();
				$accent   = '';
				$name     = ucfirst( $theme_slug );

				if ( isset( $registry[ $theme_slug ] ) ) {
					$name   = $registry[ $theme_slug ]['name'];
					$accent = $registry[ $theme_slug ]['values']['accent_color'] ?? '';
				}

				printf(
					'<span class="nlf-list-theme">'
					. '<span class="nlf-list-theme__dot" style="background:%1$s;"></span>'
					. '<span class="nlf-list-theme__name">%2$s</span>'
					. '</span>',
					esc_attr( $accent ),
					esc_html( $name )
				);
				break;
		}
	}

	/**
	 * Handle duplicate group request.
	 */
	public static function handle_duplicate_group() {
		if ( ! isset( $_GET['post'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'next-level-faq' ) );
		}

		$post_id = absint( $_GET['post'] );

		check_admin_referer( 'nlf_duplicate_group_' . $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate this group.', 'next-level-faq' ) );
		}

		$original = get_post( $post_id );

		if ( ! $original || self::POST_TYPE !== $original->post_type ) {
			wp_die( esc_html__( 'FAQ group not found.', 'next-level-faq' ) );
		}

		$new_post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'draft',
				'post_title'   => sprintf(
					/* translators: %s: original FAQ group title. */
					__( '%s (Copy)', 'next-level-faq' ),
					$original->post_title
				),
				'post_content' => $original->post_content,
			)
		);

		if ( is_wp_error( $new_post_id ) ) {
			wp_die( esc_html__( 'Failed to create duplicate.', 'next-level-faq' ) );
		}

		$meta_keys = array(
			'_nlf_faq_group_theme',
			'_nlf_faq_group_theme_custom',
			'_nlf_faq_group_settings',
			'_nlf_faq_group_use_custom_style',
			'_nlf_faq_group_custom_styles',
		);

		foreach ( $meta_keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( $value ) {
				update_post_meta( $new_post_id, $key, $value );
			}
		}

		$items = Repository::get_items_for_group( $post_id, false );

		if ( ! empty( $items ) ) {
			foreach ( $items as $index => $item ) {
				Repository::save_item(
					0,
					$new_post_id,
					$item->question,
					$item->answer,
					(int) $item->status,
					$index,
					(int) $item->initial_state,
					(int) $item->highlight
				);
			}
		}

		Cache::invalidate_group( $new_post_id );

		$redirect = add_query_arg(
			array(
				'post'              => $new_post_id,
				'action'            => 'edit',
				'nlf_group_notice'  => 'duplicated',
				'source_group'      => $post_id,
			),
			admin_url( 'post.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render custom styles section.
	 *
	 * @param bool  $use_custom_style Whether to use custom styles.
	 * @param array $custom_styles    Custom style values.
	 */
	private static function render_custom_styles( $use_custom_style, $custom_styles ) {
		$default_styles = Options::get_defaults();
		unset( $default_styles['preset'] );
		?>
		<div class="nlf-style-wrapper">
			<div class="nlf-style-toggle">
				<label>
					<input type="checkbox" name="nlf_faq_group_use_custom_style" value="1" <?php checked( ! empty( $use_custom_style ) ); ?> id="nlf-use-custom-style-toggle" />
					<strong><?php esc_html_e( 'Use custom styles for this group', 'next-level-faq' ); ?></strong>
				</label>
				<p class="description">
					<?php esc_html_e( 'Enable this to override global styles with group-specific styling.', 'next-level-faq' ); ?>
				</p>
			</div>

			<div class="nlf-custom-style-fields" style="<?php echo empty( $use_custom_style ) ? 'display: none;' : ''; ?>" data-default-styles="<?php echo esc_attr( wp_json_encode( $default_styles ) ); ?>">
				<h3><?php esc_html_e( 'Container', 'next-level-faq' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="custom_container_background"><?php esc_html_e( 'Background', 'next-level-faq' ); ?></label>
						</th>
						<td>
							<input type="text" id="custom_container_background" name="nlf_faq_group_custom_styles[container_background]" value="<?php echo esc_attr( $custom_styles['container_background'] ?? '#ffffff' ); ?>" class="nlf-color-picker" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_container_border_color"><?php esc_html_e( 'Border Color', 'next-level-faq' ); ?></label>
						</th>
						<td>
							<input type="text" id="custom_container_border_color" name="nlf_faq_group_custom_styles[container_border_color]" value="<?php echo esc_attr( $custom_styles['container_border_color'] ?? '#e2e8f0' ); ?>" class="nlf-color-picker" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_container_border_radius"><?php esc_html_e( 'Border Radius (px)', 'next-level-faq' ); ?></label>
						</th>
						<td>
							<input type="number" min="0" id="custom_container_border_radius" name="nlf_faq_group_custom_styles[container_border_radius]" value="<?php echo esc_attr( $custom_styles['container_border_radius'] ?? 8 ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_container_padding"><?php esc_html_e( 'Padding (px)', 'next-level-faq' ); ?></label>
						</th>
						<td>
							<input type="number" min="0" id="custom_container_padding" name="nlf_faq_group_custom_styles[container_padding]" value="<?php echo esc_attr( $custom_styles['container_padding'] ?? 24 ); ?>" />
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Question', 'next-level-faq' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="custom_question_color"><?php esc_html_e( 'Color', 'next-level-faq' ); ?></label>
						</th>
						<td>
							<input type="text" id="custom_question_color" name="nlf_faq_group_custom_styles[question_color]" value="<?php echo esc_attr( $custom_styles['question_color'] ?? '#0f172a' ); ?>" class="nlf-color-picker" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_question_font_size"><?php esc_html_e( 'Font Size (px)', 'next-level-faq' ); ?></label>
						</th>
						<td>
							<input type="number" min="10" id="custom_question_font_size" name="nlf_faq_group_custom_styles[question_font_size]" value="<?php echo esc_attr( $custom_styles['question_font_size'] ?? 18 ); ?>" />
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Answer', 'next-level-faq' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="custom_answer_color"><?php esc_html_e( 'Color', 'next-level-faq' ); ?></label>
						</th>
						<td>
							<input type="text" id="custom_answer_color" name="nlf_faq_group_custom_styles[answer_color]" value="<?php echo esc_attr( $custom_styles['answer_color'] ?? '#4b5563' ); ?>" class="nlf-color-picker" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_answer_font_size"><?php esc_html_e( 'Font Size (px)', 'next-level-faq' ); ?></label>
						</th>
						<td>
							<input type="number" min="10" id="custom_answer_font_size" name="nlf_faq_group_custom_styles[answer_font_size]" value="<?php echo esc_attr( $custom_styles['answer_font_size'] ?? 16 ); ?>" />
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Accent & Animation', 'next-level-faq' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="custom_accent_color"><?php esc_html_e( 'Accent Color', 'next-level-faq' ); ?></label>
						</th>
						<td>
							<input type="text" id="custom_accent_color" name="nlf_faq_group_custom_styles[accent_color]" value="<?php echo esc_attr( $custom_styles['accent_color'] ?? '#3b82f6' ); ?>" class="nlf-color-picker" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_icon_style"><?php esc_html_e( 'Icon Style', 'next-level-faq' ); ?></label>
						</th>
						<td>
							<select id="custom_icon_style" name="nlf_faq_group_custom_styles[icon_style]">
								<option value="plus_minus" <?php selected( $custom_styles['icon_style'] ?? 'plus_minus', 'plus_minus' ); ?>><?php esc_html_e( 'Plus / Minus', 'next-level-faq' ); ?></option>
								<option value="chevron" <?php selected( $custom_styles['icon_style'] ?? '', 'chevron' ); ?>><?php esc_html_e( 'Chevron', 'next-level-faq' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_animation"><?php esc_html_e( 'Animation', 'next-level-faq' ); ?></label>
						</th>
						<td>
							<select id="custom_animation" name="nlf_faq_group_custom_styles[animation]">
								<option value="slide" <?php selected( $custom_styles['animation'] ?? 'slide', 'slide' ); ?>><?php esc_html_e( 'Slide', 'next-level-faq' ); ?></option>
								<option value="fade" <?php selected( $custom_styles['animation'] ?? '', 'fade' ); ?>><?php esc_html_e( 'Fade', 'next-level-faq' ); ?></option>
								<option value="none" <?php selected( $custom_styles['animation'] ?? '', 'none' ); ?>><?php esc_html_e( 'None', 'next-level-faq' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Preview tab content.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $items   FAQ items.
	 */
	private static function render_preview_tab( $post_id, $items ) {
		?>
		<div class="nlf-preview-wrapper">
	<?php if ( empty( $items ) ) : ?>
		<!-- Empty State -->
		<div class="nlf-preview-empty-state">
			<div class="nlf-empty-icon">
				<span class="dashicons dashicons-visibility"></span>
			</div>
			<h3><?php esc_html_e( 'Preview will appear after you add items', 'next-level-faq' ); ?></h3>
			<p><?php esc_html_e( 'Add at least one question in the Content tab, then return here to see how it looks.', 'next-level-faq' ); ?></p>
			<button type="button" class="button button-primary" data-switch-tab="content">
				<?php esc_html_e( 'Go to Content Tab', 'next-level-faq' ); ?> →
			</button>
		</div>
	<?php else : ?>
				<div class="nlf-preview-controls">
					<div class="nlf-preview-device-toggle" role="radiogroup" aria-label="<?php esc_attr_e( 'Preview device', 'next-level-faq' ); ?>">
						<button type="button" class="nlf-device-btn active" data-device="desktop" aria-label="<?php esc_attr_e( 'Desktop view', 'next-level-faq' ); ?>">
							<span class="dashicons dashicons-desktop" aria-hidden="true"></span>
						</button>
						<button type="button" class="nlf-device-btn" data-device="tablet" aria-label="<?php esc_attr_e( 'Tablet view', 'next-level-faq' ); ?>">
							<span class="dashicons dashicons-tablet" aria-hidden="true"></span>
						</button>
						<button type="button" class="nlf-device-btn" data-device="mobile" aria-label="<?php esc_attr_e( 'Mobile view', 'next-level-faq' ); ?>">
							<span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
						</button>
					</div>
			<div class="nlf-preview-controls-right">
				<label class="nlf-preview-auto">
					<input type="checkbox" class="nlf-preview-auto-toggle" data-preview-auto="main" checked>
					<span><?php esc_html_e( 'Auto refresh', 'next-level-faq' ); ?></span>
				</label>
				<button type="button" class="button nlf-refresh-preview" data-refresh-preview="main">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e( 'Refresh Preview', 'next-level-faq' ); ?>
				</button>
			</div>
				</div>
				
				<div class="nlf-preview-notice">
					<span class="dashicons dashicons-info" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Save the group to see all changes reflected in the preview.', 'next-level-faq' ); ?></span>
				</div>

				<div class="nlf-preview-viewport" data-device="desktop">
			<?php Admin_UI_Components::preview_container( $post_id, 'main' ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get theme presets.
	 *
	 * @return array
	 */
	private static function get_theme_presets() {
		return array(
			// -----------------------------------------------------------
			// DEFAULT — Sophisticated neutral, flat dividers
			// -----------------------------------------------------------
			'default'      => array(
				'name'        => __( 'Default', 'next-level-faq' ),
				'description' => __( 'Refined baseline with soft shadows. Fits any site.', 'next-level-faq' ),
				'background'  => '#ffffff',
				'border'      => '#e5e7eb',
				'question'    => '#111827',
				'answer'      => '#6b7280',
				'accent'      => '#3b82f6',
				'values'      => array(
					'container_background'    => '#ffffff',
					'container_border_color'  => '#e5e7eb',
					'container_border_radius' => 12,
					'container_padding'       => 28,
					'question_color'          => '#111827',
					'question_font_size'      => 17,
					'question_font_weight'    => 600,
					'answer_color'            => '#6b7280',
					'answer_font_size'        => 15,
					'accent_color'            => '#3b82f6',
					'icon_style'              => 'plus_minus',
					'gap_between_items'       => 0,
					'shadow'                  => 'sm',
					'animation'               => 'slide',
					'layout'                  => 'flat',
				),
			),
			// -----------------------------------------------------------
			// MODERN — Floating cards, violet/indigo, generous white space
			// -----------------------------------------------------------
			'modern'       => array(
				'name'        => __( 'Modern', 'next-level-faq' ),
				'description' => __( 'Floating cards with indigo accent. Clean and airy.', 'next-level-faq' ),
				'background'  => '#ffffff',
				'border'      => '#e0e7ff',
				'question'    => '#1e1b4b',
				'answer'      => '#64748b',
				'accent'      => '#6366f1',
				'values'      => array(
					'container_background'    => '#ffffff',
					'container_border_color'  => '#e0e7ff',
					'container_border_radius' => 14,
					'container_padding'       => 0,
					'question_color'          => '#1e1b4b',
					'question_font_size'      => 17,
					'question_font_weight'    => 600,
					'answer_color'            => '#64748b',
					'answer_font_size'        => 15,
					'accent_color'            => '#6366f1',
					'icon_style'              => 'chevron',
					'gap_between_items'       => 10,
					'shadow'                  => 'md',
					'animation'               => 'slide',
					'layout'                  => 'cards',
				),
			),
			// -----------------------------------------------------------
			// ELEGANT — Warm neutrals, amber accent, stacked borders
			// -----------------------------------------------------------
			'elegant'      => array(
				'name'        => __( 'Elegant', 'next-level-faq' ),
				'description' => __( 'Warm tones with connected bordered items.', 'next-level-faq' ),
				'background'  => '#fefce8',
				'border'      => '#fde68a',
				'question'    => '#422006',
				'answer'      => '#78716c',
				'accent'      => '#d97706',
				'values'      => array(
					'container_background'    => '#fefce8',
					'container_border_color'  => '#fde68a',
					'container_border_radius' => 12,
					'container_padding'       => 28,
					'question_color'          => '#422006',
					'question_font_size'      => 17,
					'question_font_weight'    => 600,
					'answer_color'            => '#78716c',
					'answer_font_size'        => 15,
					'accent_color'            => '#d97706',
					'icon_style'              => 'arrow',
					'gap_between_items'       => 0,
					'shadow'                  => false,
					'animation'               => 'slide',
					'layout'                  => 'bordered',
				),
			),
			// -----------------------------------------------------------
			// MINIMAL — Monochrome, no decoration, content-first
			// -----------------------------------------------------------
			'minimal'      => array(
				'name'        => __( 'Minimal', 'next-level-faq' ),
				'description' => __( 'Stripped to essentials. Content speaks for itself.', 'next-level-faq' ),
				'background'  => '#ffffff',
				'border'      => '#e5e5e5',
				'question'    => '#18181b',
				'answer'      => '#52525b',
				'accent'      => '#18181b',
				'values'      => array(
					'container_background'    => '#ffffff',
					'container_border_color'  => '#e5e5e5',
					'container_border_radius' => 0,
					'container_padding'       => 24,
					'question_color'          => '#18181b',
					'question_font_size'      => 17,
					'question_font_weight'    => 500,
					'answer_color'            => '#52525b',
					'answer_font_size'        => 15,
					'accent_color'            => '#18181b',
					'icon_style'              => 'chevron',
					'gap_between_items'       => 0,
					'shadow'                  => false,
					'animation'               => 'slide',
					'layout'                  => 'clean',
				),
			),
			// -----------------------------------------------------------
			// BOLD — Emerald cards, prominent elevation
			// -----------------------------------------------------------
			'bold'         => array(
				'name'        => __( 'Bold', 'next-level-faq' ),
				'description' => __( 'Strong cards with emerald accent. High visual impact.', 'next-level-faq' ),
				'background'  => '#ffffff',
				'border'      => '#d1fae5',
				'question'    => '#064e3b',
				'answer'      => '#4b5563',
				'accent'      => '#059669',
				'values'      => array(
					'container_background'    => '#ffffff',
					'container_border_color'  => '#d1fae5',
					'container_border_radius' => 14,
					'container_padding'       => 0,
					'question_color'          => '#064e3b',
					'question_font_size'      => 18,
					'question_font_weight'    => 700,
					'answer_color'            => '#4b5563',
					'answer_font_size'        => 15,
					'accent_color'            => '#059669',
					'icon_style'              => 'plus_minus',
					'gap_between_items'       => 10,
					'shadow'                  => 'lg',
					'animation'               => 'slide',
					'layout'                  => 'cards',
				),
			),
			// -----------------------------------------------------------
			// PROFESSIONAL — Corporate blue, alternating rows
			// -----------------------------------------------------------
			'professional' => array(
				'name'        => __( 'Professional', 'next-level-faq' ),
				'description' => __( 'Structured alternating rows. Enterprise-ready.', 'next-level-faq' ),
				'background'  => '#f8fafc',
				'border'      => '#cbd5e1',
				'question'    => '#0f172a',
				'answer'      => '#475569',
				'accent'      => '#2563eb',
				'values'      => array(
					'container_background'    => '#f8fafc',
					'container_border_color'  => '#cbd5e1',
					'container_border_radius' => 10,
					'container_padding'       => 24,
					'question_color'          => '#0f172a',
					'question_font_size'      => 17,
					'question_font_weight'    => 600,
					'answer_color'            => '#475569',
					'answer_font_size'        => 15,
					'accent_color'            => '#2563eb',
					'icon_style'              => 'chevron',
					'gap_between_items'       => 0,
					'shadow'                  => 'sm',
					'animation'               => 'slide',
					'layout'                  => 'striped',
				),
			),
		);
	}

	/**
	 * Resolve a group's theme selection into a full CSS-compatible options array.
	 *
	 * Reads the group theme slug and optional custom color overrides from post meta,
	 * then merges them into a complete options array that Style_Generator can use.
	 *
	 * @param int $group_id Group post ID.
	 * @return array|null Full options array or null if no group theme is set.
	 */
	public static function resolve_group_theme_options( $group_id ) {
		$group_id = absint( $group_id );

		if ( ! $group_id ) {
			return null;
		}

		$theme_slug   = get_post_meta( $group_id, '_nlf_faq_group_theme', true );
		$theme_custom = get_post_meta( $group_id, '_nlf_faq_group_theme_custom', true );

		if ( empty( $theme_slug ) || 'default' === $theme_slug ) {
			// No group theme override — fall back to global styles if no custom colors.
			if ( empty( $theme_custom ) || ! is_array( $theme_custom ) || ! array_filter( $theme_custom ) ) {
				return null;
			}
		}

		$themes = self::get_theme_presets();
		$theme  = isset( $themes[ $theme_slug ] ) ? $themes[ $theme_slug ] : $themes['default'];
		$values = isset( $theme['values'] ) ? $theme['values'] : array();

		// Start from global defaults, then apply theme values.
		$defaults = Presets::get_default_values();
		$options  = wp_parse_args( $values, $defaults );

		// Apply custom color overrides if set.
		if ( is_array( $theme_custom ) ) {
			$color_map = array(
				'primary'    => 'question_color',
				'secondary'  => 'answer_color',
				'accent'     => 'accent_color',
				'background' => 'container_background',
			);

			foreach ( $color_map as $custom_key => $option_key ) {
				if ( ! empty( $theme_custom[ $custom_key ] ) && sanitize_hex_color( $theme_custom[ $custom_key ] ) ) {
					$options[ $option_key ] = sanitize_hex_color( $theme_custom[ $custom_key ] );
				}
			}
		}

		return $options;
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			'accordion_mode'  => false,
			'initial_state'   => 'all_closed',
			'animation_speed' => 'normal',
			'show_search'     => false,
			'show_counter'    => false,
			'smooth_scroll'   => true,
		);
	}

	/**
	 * Save metabox data for a group.
	 *
	 * SECURITY:
	 * - Nonce verification via wp_verify_nonce().
	 * - Capability check via current_user_can('edit_post') — resolved to edit_page by WP meta-cap mapping.
	 * - Autosave and post type validation.
	 * - Input sanitization via sanitize_text_field() and wp_kses_post().
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_metabox( $post_id, $post ) {
		if ( ! isset( $_POST['nlf_faq_group_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nlf_faq_group_nonce'] ) ), 'nlf_faq_group_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( self::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save FAQ Items.
		$ids       = isset( $_POST['nlf_faq_group_item_id'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_item_id'] ) ) : array();
		$questions = isset( $_POST['nlf_faq_group_question'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_question'] ) ) : array();
		$answers   = isset( $_POST['nlf_faq_group_answer'] ) ? array_map( 'wp_kses_post', wp_unslash( (array) $_POST['nlf_faq_group_answer'] ) ) : array();
		$visible   = isset( $_POST['nlf_faq_group_visible'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_visible'] ) ) : array();
		$open      = isset( $_POST['nlf_faq_group_open'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_open'] ) ) : array();
		$highlight = isset( $_POST['nlf_faq_group_highlight'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_highlight'] ) ) : array();

		$keep_ids = array();

		$count = max( count( $questions ), count( $answers ), count( $ids ) );

		for ( $i = 0; $i < $count; $i++ ) {
			$id       = isset( $ids[ $i ] ) ? (int) $ids[ $i ] : 0;
			$question = isset( $questions[ $i ] ) ? $questions[ $i ] : '';
			$answer   = isset( $answers[ $i ] ) ? $answers[ $i ] : '';

			// Skip empty entries.
			if ( '' === trim( $question ) && '' === trim( wp_strip_all_tags( $answer ) ) ) {
				continue;
			}

			$status        = isset( $visible[ (string) $i ] ) ? 1 : 0;
			$initial_state = isset( $open[ (string) $i ] ) ? 1 : 0;
			$is_highlight  = isset( $highlight[ (string) $i ] ) ? 1 : 0;

			$new_id     = Repository::save_item( $id, $post_id, $question, $answer, $status, $i, $initial_state, $is_highlight );
			$keep_ids[] = $new_id;
		}

		Repository::delete_all_except( $keep_ids, $post_id );

		// Save Theme.
		if ( isset( $_POST['nlf_faq_group_theme'] ) ) {
			update_post_meta( $post_id, '_nlf_faq_group_theme', sanitize_text_field( wp_unslash( $_POST['nlf_faq_group_theme'] ) ) );
		}

		if ( isset( $_POST['nlf_faq_group_theme_custom'] ) && is_array( $_POST['nlf_faq_group_theme_custom'] ) ) {
			$theme_custom = array_map( 'sanitize_hex_color', wp_unslash( $_POST['nlf_faq_group_theme_custom'] ) );
			update_post_meta( $post_id, '_nlf_faq_group_theme_custom', $theme_custom );
		}

		// Save Settings.
		if ( isset( $_POST['nlf_faq_group_settings'] ) && is_array( $_POST['nlf_faq_group_settings'] ) ) {
			$settings           = wp_unslash( $_POST['nlf_faq_group_settings'] );
			$sanitized_settings = array(
				'accordion_mode'  => ! empty( $settings['accordion_mode'] ),
				'initial_state'   => in_array( $settings['initial_state'] ?? '', array( 'all_closed', 'first_open', 'custom' ), true ) ? $settings['initial_state'] : 'all_closed',
				'animation_speed' => in_array( $settings['animation_speed'] ?? '', array( 'fast', 'normal', 'slow' ), true ) ? $settings['animation_speed'] : 'normal',
				'show_search'     => ! empty( $settings['show_search'] ),
				'show_counter'    => ! empty( $settings['show_counter'] ),
				'smooth_scroll'   => ! empty( $settings['smooth_scroll'] ),
			);
			update_post_meta( $post_id, '_nlf_faq_group_settings', $sanitized_settings );
		}

		// Save Custom Style Settings.
		$use_custom_style = ! empty( $_POST['nlf_faq_group_use_custom_style'] );
		update_post_meta( $post_id, '_nlf_faq_group_use_custom_style', $use_custom_style );

		if ( $use_custom_style && isset( $_POST['nlf_faq_group_custom_styles'] ) && is_array( $_POST['nlf_faq_group_custom_styles'] ) ) {
			$custom_styles = Options::sanitize( wp_unslash( $_POST['nlf_faq_group_custom_styles'] ) );
			update_post_meta( $post_id, '_nlf_faq_group_custom_styles', $custom_styles );

			// Generate group-specific CSS.
			if ( class_exists( 'Krslys\NextLevelFaq\Style_Generator' ) ) {
				Style_Generator::generate_and_save_for_group( $post_id, $custom_styles );
			}
		} else {
			// Remove group-specific CSS if custom styles disabled.
			delete_post_meta( $post_id, '_nlf_faq_group_custom_styles' );
			if ( class_exists( 'Krslys\NextLevelFaq\Style_Generator' ) ) {
				Style_Generator::delete_group_css( $post_id );
			}
		}
		Cache::invalidate_group( $post_id );
	}

	/**
	 * Handle deletion of a group.
	 *
	 * SECURITY: Validates post type before deletion.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function handle_delete( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		Repository::delete_items_for_group( $post_id );
		Cache::invalidate_group( $post_id );
	}

	/**
	 * AJAX handler for live preview.
	 *
	 * SECURITY: Nonce verification and capability check.
	 */
	public static function ajax_get_group_preview() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nlf_group_preview' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'next-level-faq' ) ) );
		}

		// Check capability
		if ( ! current_user_can( 'edit_nlf_faq_groups' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'next-level-faq' ) ) );
		}

		$group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;

		if ( ! $group_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid group ID.', 'next-level-faq' ) ) );
		}

		// Get group items
		$items = Repository::get_items_for_group( $group_id );

		if ( empty( $items ) ) {
			wp_send_json_success(
				array(
					'html' => '<div class="nlf-live-view-empty"><p>' . esc_html__( 'No FAQ items found. Add some items in the FAQ Items tab.', 'next-level-faq' ) . '</p></div>',
				)
			);
		}

		// Get settings
		$settings         = get_post_meta( $group_id, '_nlf_faq_group_settings', true );
		$use_custom_style = get_post_meta( $group_id, '_nlf_faq_group_use_custom_style', true );

		if ( ! is_array( $settings ) ) {
			$settings = self::get_default_settings();
		}

		// Resolve theme styles for the preview.
		// Accept unsaved theme from AJAX request for live preview.
		$preview_theme = isset( $_POST['theme'] ) ? sanitize_key( wp_unslash( $_POST['theme'] ) ) : '';
		$preview_custom = array();
		$color_keys = array( 'primary', 'secondary', 'accent', 'background' );
		foreach ( $color_keys as $key ) {
			$post_key = 'theme_custom_' . $key;
			if ( ! empty( $_POST[ $post_key ] ) ) {
				$preview_custom[ $key ] = sanitize_hex_color( wp_unslash( $_POST[ $post_key ] ) );
			}
		}

		$inline_style    = '';
		$icon_style      = 'plus_minus';
		$preview_options = null;
		$faq_classes     = array( 'nlf-faq', 'nlf-faq--preview' );

		if ( empty( $use_custom_style ) ) {

			if ( $preview_theme ) {
				// Use the theme slug sent from the UI (may not be saved yet).
				$themes = self::get_theme_presets();
				$theme  = isset( $themes[ $preview_theme ] ) ? $themes[ $preview_theme ] : $themes['default'];
				$values = isset( $theme['values'] ) ? $theme['values'] : array();
				$defaults = Presets::get_default_values();
				$preview_options = wp_parse_args( $values, $defaults );

				// Apply preview custom colors.
				$color_map = array(
					'primary'    => 'question_color',
					'secondary'  => 'answer_color',
					'accent'     => 'accent_color',
					'background' => 'container_background',
				);
				foreach ( $color_map as $custom_key => $option_key ) {
					if ( ! empty( $preview_custom[ $custom_key ] ) ) {
						$preview_options[ $option_key ] = $preview_custom[ $custom_key ];
					}
				}
			} else {
				$preview_options = self::resolve_group_theme_options( $group_id );
			}

			if ( is_array( $preview_options ) ) {
				$inline_style = Style_Generator::build_inline_style( $preview_options );
				$icon_style   = isset( $preview_options['icon_style'] ) ? $preview_options['icon_style'] : 'plus_minus';
			} else {
				// Fall back to global preset.
				$global_options = Options::get_resolved_options();
				$inline_style   = Style_Generator::build_inline_style( $global_options );
				$icon_style     = isset( $global_options['icon_style'] ) ? $global_options['icon_style'] : 'plus_minus';
			}
		}

		if ( 'chevron' === $icon_style ) {
			$faq_classes[] = 'nlf-faq--icon-chevron';
		} elseif ( 'arrow' === $icon_style ) {
			$faq_classes[] = 'nlf-faq--icon-arrow';
		}

		// Determine layout class from preview options.
		$preview_layout = 'flat';
		if ( is_array( $preview_options ) && isset( $preview_options['layout'] ) ) {
			$preview_layout = $preview_options['layout'];
		} elseif ( $preview_theme ) {
			$themes_for_layout = self::get_theme_presets();
			$t = isset( $themes_for_layout[ $preview_theme ] ) ? $themes_for_layout[ $preview_theme ] : null;
			if ( $t && isset( $t['values']['layout'] ) ) {
				$preview_layout = $t['values']['layout'];
			}
		}
		if ( 'flat' !== $preview_layout ) {
			$faq_classes[] = 'nlf-faq--layout-' . sanitize_html_class( $preview_layout );
		}

		// Build FAQ HTML
		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $faq_classes ) ); ?>" 
			data-group-id="<?php echo esc_attr( $group_id ); ?>"
			data-accordion="<?php echo ! empty( $settings['accordion_mode'] ) ? '1' : '0'; ?>"
			data-animation-speed="<?php echo esc_attr( $settings['animation_speed'] ?? 'normal' ); ?>"
			data-smooth-scroll="<?php echo ! empty( $settings['smooth_scroll'] ) ? '1' : '0'; ?>"
			<?php if ( $inline_style ) : ?>
				style="<?php echo esc_attr( $inline_style ); ?>"
			<?php endif; ?>>
			<?php if ( ! empty( $settings['show_search'] ) ) : ?>
				<div class="nlf-faq-search">
					<input type="text" class="nlf-faq-search-input" placeholder="<?php esc_attr_e( 'Search FAQs...', 'next-level-faq' ); ?>" />
				</div>
			<?php endif; ?>

			<?php foreach ( $items as $index => $item ) : ?>
				<?php
				$is_open      = ! empty( $item->initial_state ) && 'custom' === ( $settings['initial_state'] ?? 'all_closed' );
				$is_first     = 0 === $index && 'first_open' === ( $settings['initial_state'] ?? 'all_closed' );
				$is_highlight = ! empty( $item->highlight );
				?>
				<div class="nlf-faq__item <?php echo esc_attr( ( $is_open || $is_first ) ? 'is-open' : '' ); ?> <?php echo esc_attr( $is_highlight ? 'nlf-faq__item--highlight' : '' ); ?>" data-faq-id="<?php echo esc_attr( $item->id ); ?>">
					<div class="nlf-faq__question">
						<?php if ( ! empty( $settings['show_counter'] ) ) : ?>
							<span class="nlf-faq__counter"><?php echo esc_html( $index + 1 ); ?>.</span>
						<?php endif; ?>
						<span><?php echo esc_html( $item->question ); ?></span>
						<span class="nlf-faq__icon" aria-hidden="true"></span>
					</div>
					<div class="nlf-faq__answer">
						<?php echo wp_kses_post( $item->answer ); ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Append custom notice flag after saving.
	 *
	 * @param string $location Redirect URL.
	 * @param int    $post_id  Post ID.
	 *
	 * @return string
	 */
	public static function append_save_notice_flag( $location, $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return $location;
		}

		// If WordPress wants to redirect to edit.php (post list), 
		// redirect to post.php (edit page) instead, but PRESERVE all query args
		if ( strpos( $location, 'edit.php' ) !== false ) {
			// Parse the original URL to extract query parameters
			$parsed = parse_url( $location );
			parse_str( $parsed['query'] ?? '', $query_args );
			
			// Rebuild URL to post.php while keeping WordPress's parameters
			$location = add_query_arg(
				array_merge(
					array(
						'post'   => $post_id,
						'action' => 'edit',
					),
					$query_args // Preserve WordPress's status messages
				),
				admin_url( 'post.php' )
			);
		}

		// Add custom notice flag
		return add_query_arg(
			array(
				'nlf_group_notice' => 'saved',
			),
			$location
		);
	}

	/**
	 * Render contextual success message.
	 */
	public static function render_save_notice() {
		$notice = '';

		if ( isset( $_GET['nlf_group_notice'] ) ) {
			$notice = sanitize_key( wp_unslash( $_GET['nlf_group_notice'] ) );
		} elseif ( isset( $_GET['nlf_group_saved'] ) && '1' === $_GET['nlf_group_saved'] ) {
			$notice = 'saved';
		}

		if ( '' === $notice ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->base || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$message = '';
		$type    = 'success';

		switch ( $notice ) {
			case 'duplicated':
				$source_id = isset( $_GET['source_group'] ) ? absint( $_GET['source_group'] ) : 0;
				$source    = $source_id ? get_the_title( $source_id ) : '';
				$message   = $source
					? sprintf(
						/* translators: %s: Source group title. */
						__( 'FAQ group duplicated from "%s". Review and publish when ready.', 'next-level-faq' ),
						$source
					)
					: __( 'FAQ group duplicated. Review and publish when ready.', 'next-level-faq' );
				break;
			case 'saved':
			default:
				$message = __( 'FAQ group updated. Your changes are now live.', 'next-level-faq' );
				break;
		}

		?>
		<div class="notice notice-<?php echo esc_attr( 'error' === $type ? 'error' : 'success' ); ?> is-dismissible nlf-success-banner">
			<p>
				<span class="dashicons <?php echo 'error' === $type ? 'dashicons-warning' : 'dashicons-yes-alt'; ?>" aria-hidden="true"></span>
				<strong><?php echo esc_html( $message ); ?></strong>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle AJAX save for FAQ group.
	 */
	public static function handle_ajax_save_group() {
		// Verify nonce
		if ( ! isset( $_POST['nlf_faq_group_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nlf_faq_group_nonce'] ) ), 'nlf_faq_group_save' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'next-level-faq' ) ),
				403
			);
		}

		// Get post ID
		$post_id = isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid post ID.', 'next-level-faq' ) ),
				400
			);
		}

		// Check capability
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to edit this FAQ group.', 'next-level-faq' ) ),
				403
			);
		}

		// Get the post
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid FAQ group.', 'next-level-faq' ) ),
				400
			);
		}

		// Build update args.
		$update_args = array( 'ID' => $post_id );

		if ( isset( $_POST['post_title'] ) ) {
			$update_args['post_title'] = sanitize_text_field( wp_unslash( $_POST['post_title'] ) );
		}

		// Handle post status change.
		if ( isset( $_POST['post_status'] ) ) {
			$new_status = sanitize_key( $_POST['post_status'] );
			$allowed    = array( 'publish', 'draft', 'pending', 'private' );

			if ( in_array( $new_status, $allowed, true ) ) {
				if ( 'publish' === $new_status && ! current_user_can( 'publish_nlf_faq_groups' ) ) {
					$new_status = 'pending';
				}
				$update_args['post_status'] = $new_status;
			}
		}

		if ( count( $update_args ) > 1 ) {
			wp_update_post( $update_args );
		}

		// Call the existing save_metabox method to handle all the meta data.
		self::save_metabox( $post_id, $post );

		// Get the final post status after update.
		$updated_post = get_post( $post_id );

		// Send success response.
		wp_send_json_success(
			array(
				'message'     => __( 'FAQ group saved successfully!', 'next-level-faq' ),
				'post_id'     => $post_id,
				'post_status' => $updated_post->post_status,
			)
		);
	}
}
