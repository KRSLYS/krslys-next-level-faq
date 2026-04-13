<?php
/**
 * FAQ Group admin pages (custom table based).
 *
 * Replaces the old Group_CPT class. Uses custom tables
 * (wp_krslys_nlfa_groups) via Groups_Repository instead of
 * WordPress CPT (wp_posts / wp_postmeta).
 *
 * @package Krslys\NextLevelFaqAccordion
 */

namespace Krslys\NextLevelFaqAccordion;

use Krslys\NextLevelFaqAccordion\Admin_UI_Components;
use Krslys\NextLevelFaqAccordion\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Group_Admin class.
 *
 * Manages FAQ groups using custom admin pages backed by the
 * wp_krslys_nlfa_groups custom table.
 *
 * SECURITY FEATURES:
 * - All saves protected with nonce verification.
 * - Capability checks via manage_krslys_nlfa.
 * - Input sanitization via sanitize_text_field() and wp_kses_post().
 * - Output escaping via esc_attr(), esc_html().
 */
class Group_Admin {

	/**
	 * Bootstrap all group-admin hooks.
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'register_admin_pages' ), 20 );
		add_action( 'wp_ajax_nlf_save_faq_group_ajax', array( __CLASS__, 'handle_ajax_save_group' ) );
	}

	/* ─────────────────────────────────────────────
	 * 2. register_admin_pages() — submenu pages (admin_menu)
	 * ───────────────────────────────────────────── */

	/**
	 * Register admin submenu pages under nlf-faq.
	 *
	 * Called on `admin_menu`.
	 */
	public static function register_admin_pages() {
		// Hidden submenu page for editing / adding a single group.
		// Note: the visible "FAQ Groups" entry is registered by Admin_Settings::register_menu()
		// to control menu ordering. Only the hidden edit page lives here.
		add_submenu_page(
			'', // hidden (no parent — empty string avoids PHP 8 null deprecation)
			__( 'Edit FAQ Group', 'krslys-next-level-faq-accordion' ),
			__( 'Edit FAQ Group', 'krslys-next-level-faq-accordion' ),
			Admin_Settings::CAPABILITY,
			'nlf-faq-group-edit',
			array( __CLASS__, 'render_edit_page' )
		);

		// Handle URL-based actions from the list page.
		self::handle_list_actions();

		// Handle form POST saves early (before headers are sent).
		add_action( 'admin_init', array( __CLASS__, 'maybe_handle_save' ) );

		// Enqueue assets.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

		// Add legacy body class so existing CSS selectors keep working.
		add_filter( 'admin_body_class', array( __CLASS__, 'add_body_class' ) );
	}

	/**
	 * Add body class to our admin pages for CSS scoping.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public static function add_body_class( $classes ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page routing for body class only.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( in_array( $page, array( 'nlf-faq-groups', 'nlf-accordion-groups', 'nlf-faq-group-edit' ), true ) ) {
			$classes .= ' nlf-faq-admin-page';
		}

		return $classes;
	}

	/**
	 * Handle URL-based actions (delete, duplicate) from the list page.
	 */
	private static function handle_list_actions() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page routing check only; nonce verified below for state-changing actions.
		if ( ! isset( $_GET['page'] ) || ! in_array( $_GET['page'], array( 'nlf-faq-groups', 'nlf-accordion-groups' ), true ) ) {
			return;
		}

		// Determine which list page we are on.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page routing check only.
		$current_list_page = sanitize_key( wp_unslash( $_GET['page'] ) );

		// Delete action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified immediately below.
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified immediately below.
			$group_id = absint( $_GET['id'] );

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'nlf_delete_group_' . $group_id ) ) {
				wp_die( esc_html__( 'Security check failed.', 'krslys-next-level-faq-accordion' ) );
			}

			if ( ! Admin_Settings::current_user_can_manage() ) {
				wp_die( esc_html__( 'You do not have permission to delete this group.', 'krslys-next-level-faq-accordion' ) );
			}

			Style_Generator::delete_group_css( $group_id );
			Groups_Repository::delete_group( $group_id );

			wp_safe_redirect( add_query_arg( array(
				'page'             => $current_list_page,
				'nlf_group_notice' => 'deleted',
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Duplicate action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified immediately below.
		if ( isset( $_GET['action'] ) && 'duplicate' === $_GET['action'] && isset( $_GET['id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified immediately below.
			$group_id = absint( $_GET['id'] );

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'nlf_duplicate_group_' . $group_id ) ) {
				wp_die( esc_html__( 'Security check failed.', 'krslys-next-level-faq-accordion' ) );
			}

			if ( ! Admin_Settings::current_user_can_manage() ) {
				wp_die( esc_html__( 'You do not have permission to duplicate this group.', 'krslys-next-level-faq-accordion' ) );
			}

			$original = Groups_Repository::get_group_by_id( $group_id );

			if ( ! $original ) {
				wp_die( esc_html__( 'FAQ group not found.', 'krslys-next-level-faq-accordion' ) );
			}

			$duplicate_type = 'nlf-accordion-groups' === $current_list_page ? 'accordion' : 'faq';

			$new_id = Groups_Repository::create_group( array(
				'title'            => sprintf(
					/* translators: %s: original FAQ group title. */
					__( '%s (Copy)', 'krslys-next-level-faq-accordion' ),
					$original->title
				),
				'type'             => $duplicate_type,
				'theme_settings'   => $original->theme_settings,
				'display_settings' => $original->display_settings,
				'custom_styles'    => $original->custom_styles,
				'use_custom_style' => $original->use_custom_style,
				'status'           => 'active',
			) );

			if ( ! $new_id ) {
				wp_die( esc_html__( 'Failed to create duplicate.', 'krslys-next-level-faq-accordion' ) );
			}

			// Duplicate items.
			$items = Repository::get_items_for_group( $group_id, false );

			if ( ! empty( $items ) ) {
				foreach ( $items as $index => $item ) {
					Repository::save_item(
						0,
						$new_id,
						$item->question,
						$item->answer,
						(int) $item->status,
						$index,
						(int) $item->initial_state,
						(int) $item->highlight
					);
				}
			}

			Cache::invalidate_group( $new_id );

			wp_safe_redirect( add_query_arg( array(
				'page'             => 'nlf-faq-group-edit',
				'id'               => $new_id,
				'type'             => $duplicate_type,
				'nlf_group_notice' => 'duplicated',
			), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	/* ─────────────────────────────────────────────
	 * 3. render_list_page() — Groups list table
	 * ───────────────────────────────────────────── */

	/**
	 * Render the FAQ/Accordion Groups list table page.
	 *
	 * @param string $type Content type ('faq' or 'accordion').
	 */
	public static function render_list_page( $type = 'faq' ) {
		if ( ! class_exists( '\WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		$list_table = new Group_List_Table( $type );
		$list_table->prepare_items();

		$add_url    = admin_url( 'admin.php?page=nlf-faq-group-edit&id=0&type=' . $type );
		$list_slug  = 'accordion' === $type ? 'nlf-accordion-groups' : 'nlf-faq-groups';
		$page_label = 'accordion' === $type ? __( 'Accordion Groups', 'krslys-next-level-faq-accordion' ) : __( 'FAQ Groups', 'krslys-next-level-faq-accordion' );

		// Render notices.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice parameter, not used for data modification.
		if ( isset( $_GET['nlf_group_notice'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice parameter.
			$notice = sanitize_key( wp_unslash( $_GET['nlf_group_notice'] ) );
			$message = '';

			if ( 'accordion' === $type ) {
				switch ( $notice ) {
					case 'deleted':
						$message = __( 'Accordion group deleted.', 'krslys-next-level-faq-accordion' );
						break;
					case 'duplicated':
						$message = __( 'Accordion group duplicated. You can now edit it.', 'krslys-next-level-faq-accordion' );
						break;
				}
			} else {
				switch ( $notice ) {
					case 'deleted':
						$message = __( 'FAQ group deleted.', 'krslys-next-level-faq-accordion' );
						break;
					case 'duplicated':
						$message = __( 'FAQ group duplicated. You can now edit it.', 'krslys-next-level-faq-accordion' );
						break;
				}
			}

			if ( $message ) {
				echo '<div class="notice notice-success is-dismissible nlf-success-banner"><p>'
					. '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> '
					. '<strong>' . esc_html( $message ) . '</strong>'
					. '</p></div>';
			}
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( $page_label ); ?></h1>
			<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'krslys-next-level-faq-accordion' ); ?></a>
			<hr class="wp-header-end" />

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $list_slug ); ?>" />
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/* ─────────────────────────────────────────────
	 * 4. render_edit_page() — Single group editor
	 * ───────────────────────────────────────────── */

	/**
	 * Render the edit / add page for a single FAQ group.
	 */
	public static function render_edit_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only; nonce checked on form submit.
		$group_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$type     = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : 'faq'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$state = Groups_Repository::get_full_group_state( $group_id );

		if ( 'accordion' === $type ) {
			$page_title = $group_id ? __( 'Edit Accordion Group', 'krslys-next-level-faq-accordion' ) : __( 'Add New Accordion Group', 'krslys-next-level-faq-accordion' );
			$list_url   = admin_url( 'admin.php?page=nlf-accordion-groups' );
		} else {
			$page_title = $group_id ? __( 'Edit FAQ Group', 'krslys-next-level-faq-accordion' ) : __( 'Add New FAQ Group', 'krslys-next-level-faq-accordion' );
			$list_url   = admin_url( 'admin.php?page=nlf-faq-groups' );
		}

		// Localize script data.
		if ( 'accordion' === $type ) {
			$i18n_labels = array(
				'saving'         => __( 'Saving...', 'krslys-next-level-faq-accordion' ),
				'saved'          => __( 'Saved!', 'krslys-next-level-faq-accordion' ),
				'update'         => __( 'Update', 'krslys-next-level-faq-accordion' ),
				'title_required' => __( 'Please enter a title for this accordion group.', 'krslys-next-level-faq-accordion' ),
				'edit_title'     => __( 'Edit Accordion Group', 'krslys-next-level-faq-accordion' ),
				'created'        => __( 'Accordion group created.', 'krslys-next-level-faq-accordion' ),
			);
		} else {
			$i18n_labels = array(
				'saving'         => __( 'Saving...', 'krslys-next-level-faq-accordion' ),
				'saved'          => __( 'Saved!', 'krslys-next-level-faq-accordion' ),
				'update'         => __( 'Update', 'krslys-next-level-faq-accordion' ),
				'title_required' => __( 'Please enter a title for this FAQ group.', 'krslys-next-level-faq-accordion' ),
				'edit_title'     => __( 'Edit FAQ Group', 'krslys-next-level-faq-accordion' ),
				'created'        => __( 'FAQ group created.', 'krslys-next-level-faq-accordion' ),
			);
		}

		wp_localize_script( 'nlf-faq-group-metabox', 'nlfGroupData', array(
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'saveNonce'  => wp_create_nonce( 'nlf_faq_group_save' ),
			'groupId'    => $group_id,
			'groupState' => $state,
			'themes'     => self::get_presets_for_js(),
			'editUrl'    => admin_url( 'admin.php?page=nlf-faq-group-edit' ),
			'listUrl'    => $list_url,
			'isDebug'    => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'type'       => $type,
			'i18n'       => $i18n_labels,
		) );

		// Render notices.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice parameter.
		if ( isset( $_GET['nlf_group_notice'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice parameter.
			$notice = sanitize_key( wp_unslash( $_GET['nlf_group_notice'] ) );
			$message      = '';
			$notice_level = 'success';
			$is_accordion = 'accordion' === $type;

			switch ( $notice ) {
				case 'saved':
					$message = $is_accordion
						? __( 'Accordion group updated. Your changes are now live.', 'krslys-next-level-faq-accordion' )
						: __( 'FAQ group updated. Your changes are now live.', 'krslys-next-level-faq-accordion' );
					break;
				case 'created':
					$message = $is_accordion
						? __( 'Accordion group created.', 'krslys-next-level-faq-accordion' )
						: __( 'FAQ group created.', 'krslys-next-level-faq-accordion' );
					break;
				case 'duplicated':
					$message = $is_accordion
						? __( 'Accordion group duplicated. Review and publish when ready.', 'krslys-next-level-faq-accordion' )
						: __( 'FAQ group duplicated. Review and publish when ready.', 'krslys-next-level-faq-accordion' );
					break;
				case 'title_required':
					$message = $is_accordion
						? __( 'Title is required. Please enter a title for this accordion group.', 'krslys-next-level-faq-accordion' )
						: __( 'Title is required. Please enter a title for this FAQ group.', 'krslys-next-level-faq-accordion' );
					$notice_level = 'error';
					break;
			}

			if ( $message ) {
				$icon = 'error' === $notice_level ? 'dashicons-warning' : 'dashicons-yes-alt';
				echo '<div class="notice notice-' . esc_attr( $notice_level ) . ' is-dismissible nlf-success-banner"><p>'
					. '<span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span> '
					. '<strong>' . esc_html( $message ) . '</strong>'
					. '</p></div>';
			}
		}

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( $page_title ); ?></h1>
			<a href="<?php echo esc_url( $list_url ); ?>" class="page-title-action"><?php esc_html_e( 'Back to Groups', 'krslys-next-level-faq-accordion' ); ?></a>
			<hr class="wp-header-end" />

			<form method="post" id="nlf-group-edit-form">
				<?php wp_nonce_field( 'nlf_faq_group_save', 'nlf_faq_group_nonce' ); ?>
				<input type="hidden" name="group_id" value="<?php echo esc_attr( $group_id ); ?>" />
				<input type="hidden" name="nlf_faq_group_type" value="<?php echo esc_attr( $type ); ?>" />

				<!-- Title -->
				<div id="titlediv" style="margin-bottom: 20px;">
					<div id="titlewrap">
						<label class="screen-reader-text" for="nlf_group_title"><?php esc_html_e( 'Group title', 'krslys-next-level-faq-accordion' ); ?></label>
						<input type="text" name="nlf_group_title" id="nlf_group_title" value="" placeholder="<?php esc_attr_e( 'Enter group title here', 'krslys-next-level-faq-accordion' ); ?>" autocomplete="off" required style="width:100%;font-size:1.7em;padding:3px 8px;line-height:1.4;" />
					</div>
				</div>

				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-2">

						<!-- Main content -->
						<div id="post-body-content">
							<div id="nlf_faq_group_tabs" class="postbox">
								<div class="inside">
									<?php self::render_metabox_content( $group_id, $type ); ?>
								</div>
							</div>
						</div>

						<!-- Sidebar -->
						<div id="postbox-container-1" class="postbox-container">
							<!-- Save / Publish Box -->
							<div class="postbox" id="submitdiv">
								<div class="postbox-header">
									<h2 class="hndle"><?php esc_html_e( 'Publish', 'krslys-next-level-faq-accordion' ); ?></h2>
								</div>
								<div class="inside">
									<div class="submitbox" id="submitpost">
										<div id="major-publishing-actions">
											<div id="publishing-action">
												<input type="submit" name="nlf_save_group" id="publish" class="button button-primary button-large" value="<?php echo esc_attr( $group_id ? __( 'Update', 'krslys-next-level-faq-accordion' ) : __( 'Publish', 'krslys-next-level-faq-accordion' ) ); ?>" />
											</div>
											<div class="clear"></div>
										</div>
									</div>
								</div>
							</div>

							<!-- How To Use Sidebar -->
							<div class="postbox" id="nlf-how-to-use-box"<?php if ( ! $group_id ) : ?> style="display:none;"<?php endif; ?>>
								<div class="postbox-header">
									<h2 class="hndle"><?php esc_html_e( 'How To Use', 'krslys-next-level-faq-accordion' ); ?></h2>
								</div>
								<div class="inside">
									<?php self::render_how_to_use_sidebar( $group_id ); ?>
								</div>
							</div>
						</div>

					</div><!-- #post-body -->
				</div><!-- #poststuff -->

				<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
				<div class="nlf-json-debug" style="margin-top:20px;clear:both;">
					<label>
						<input type="checkbox" id="nlf-show-json-state" />
						<strong><?php esc_html_e( 'Show JSON State', 'krslys-next-level-faq-accordion' ); ?></strong>
					</label>
					<textarea id="nlf-json-state-output" readonly rows="20" style="width:100%;display:none;font-family:monospace;font-size:12px;"><?php echo esc_textarea( wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
					<button type="button" class="button button-small" id="nlf-copy-json" style="display:none;"><?php esc_html_e( 'Copy JSON', 'krslys-next-level-faq-accordion' ); ?></button>
				</div>
				<?php endif; ?>
				</form>

		</div><!-- .wrap -->
		<?php
	}

	/**
	 * Render the tabbed metabox content (reusable from edit page).
	 *
	 * @param int    $group_id Group ID.
	 * @param string $type     Content type ('faq' or 'accordion').
	 */
	private static function render_metabox_content( $group_id, $type = 'faq' ) {
		$items = Repository::get_items_for_group( $group_id, false );
		?>
		<div class="nlf-faq-group-tabs-wrapper">
			<!-- Tab Navigation with ARIA -->
			<div class="nlf-faq-tabs-nav" role="tablist" aria-label="<?php esc_attr_e( 'FAQ Group Configuration', 'krslys-next-level-faq-accordion' ); ?>">
				<button
					type="button"
					role="tab"
					class="nlf-faq-tab-button active"
					data-tab="content"
					id="tab-content"
					aria-selected="true"
					aria-controls="panel-content">
					<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
					<span class="nlf-tab-label"><?php esc_html_e( 'Content', 'krslys-next-level-faq-accordion' ); ?></span>
				</button>
				<button
					type="button"
					role="tab"
					class="nlf-faq-tab-button"
					data-tab="settings"
					id="tab-settings"
					aria-selected="false"
					aria-controls="panel-settings">
					<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
					<span class="nlf-tab-label"><?php esc_html_e( 'Settings', 'krslys-next-level-faq-accordion' ); ?></span>
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
					<span class="nlf-tab-label"><?php esc_html_e( 'Appearance', 'krslys-next-level-faq-accordion' ); ?></span>
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
					<span class="nlf-tab-label"><?php esc_html_e( 'Preview', 'krslys-next-level-faq-accordion' ); ?></span>
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
					<?php self::render_content_tab( $group_id, $items, $type ); ?>
				</div>

				<!-- Settings Tab (Behavior & Display) -->
				<div
					class="nlf-faq-tab-panel"
					data-tab="settings"
					id="panel-settings"
					role="tabpanel"
					aria-labelledby="tab-settings"
					tabindex="0"
					hidden>
					<div class="nlf-section">
						<div class="nlf-section-header">
							<h3><?php esc_html_e( 'Behavior & Display Settings', 'krslys-next-level-faq-accordion' ); ?></h3>
							<p class="description">
								<?php esc_html_e( 'Control how users interact with your FAQs.', 'krslys-next-level-faq-accordion' ); ?>
							</p>
						</div>
						<?php self::render_settings_fields( $type ); ?>
					</div>
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
					<?php self::render_appearance_tab( $group_id, $items ); ?>
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
					<?php self::render_preview_tab( $group_id, $items ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/* ─────────────────────────────────────────────
	 * 5. enqueue_admin_assets() — CSS & JS
	 * ───────────────────────────────────────────── */

	/**
	 * Enqueue admin assets for group pages.
	 *
	 * @param string $hook_suffix Hook suffix.
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page routing for asset enqueueing only.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		// List page: enqueue admin CSS + clipboard script.
		if ( in_array( $page, array( 'nlf-faq-groups', 'nlf-accordion-groups' ), true ) ) {
			wp_enqueue_style(
				'nlf-faq-admin',
				krslys_nlfa_asset_url( 'assets/css/admin-faq-style.css' ),
				array(),
				NLF_FAQ_CSS_VERSION
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

		// Edit page.
		if ( 'nlf-faq-group-edit' !== $page ) {
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
			krslys_nlfa_asset_url( 'assets/css/admin-faq-style.css' ),
			array( 'wp-color-picker' ),
			NLF_FAQ_CSS_VERSION
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
			krslys_nlfa_asset_url( 'assets/js/frontend-faq.js' ),
			array(),
			NLF_FAQ_VERSION,
			true
		);

		$js_metabox_path   = krslys_nlfa_asset_path( 'assets/js/admin-faq-group-metabox.js' );
		$js_collector_path = krslys_nlfa_asset_path( 'assets/js/admin-state-collector.js' );

		wp_enqueue_script(
			'nlf-faq-group-metabox',
			krslys_nlfa_asset_url( 'assets/js/admin-faq-group-metabox.js' ),
			array( 'wp-editor', 'wp-color-picker', 'nlf-faq-frontend' ),
			file_exists( $js_metabox_path ) ? filemtime( $js_metabox_path ) : NLF_FAQ_VERSION,
			true
		);

		wp_enqueue_script(
			'nlf-admin-state-collector',
			krslys_nlfa_asset_url( 'assets/js/admin-state-collector.js' ),
			array( 'nlf-faq-group-metabox' ),
			file_exists( $js_collector_path ) ? filemtime( $js_collector_path ) : NLF_FAQ_VERSION,
			true
		);
	}

	/* ─────────────────────────────────────────────
	 * 6. handle_save() — form POST handler
	 * ───────────────────────────────────────────── */

	/**
	 * Check if this is a form POST save and handle it before headers are sent.
	 *
	 * Called on admin_init so wp_safe_redirect() works without "headers already sent".
	 */
	public static function maybe_handle_save() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page routing check; nonce verified in handle_save().
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'nlf-faq-group-edit' !== $page || 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			return;
		}

		self::handle_save();
	}

	/**
	 * Process the form POST when saving a group.
	 */
	private static function handle_save() {
		// Verify nonce.
		if ( ! isset( $_POST['nlf_faq_group_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nlf_faq_group_nonce'] ) ), 'nlf_faq_group_save' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'krslys-next-level-faq-accordion' ) );
		}

		// Check capability.
		if ( ! Admin_Settings::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to save this group.', 'krslys-next-level-faq-accordion' ) );
		}

		$group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;

		// Title is required — redirect back with error if empty.
		$title        = isset( $_POST['nlf_group_title'] ) ? sanitize_text_field( wp_unslash( $_POST['nlf_group_title'] ) ) : '';
		$redirect_type = isset( $_POST['nlf_faq_group_type'] ) ? sanitize_key( $_POST['nlf_faq_group_type'] ) : 'faq';
		if ( '' === $title ) {
			wp_safe_redirect( add_query_arg( array(
				'page'             => 'nlf-faq-group-edit',
				'id'               => $group_id,
				'type'             => $redirect_type,
				'nlf_group_notice' => 'title_required',
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Build update data array.
		$update_data = array( 'title' => $title );

		// Theme.
		$theme_slug   = isset( $_POST['nlf_faq_group_theme'] ) ? sanitize_text_field( wp_unslash( $_POST['nlf_faq_group_theme'] ) ) : 'default';
		$theme_custom = isset( $_POST['nlf_faq_group_theme_custom'] ) ? array_map( 'sanitize_hex_color', wp_unslash( $_POST['nlf_faq_group_theme_custom'] ) ) : array();
		$update_data['theme_settings'] = array( 'theme' => $theme_slug, 'custom_colors' => $theme_custom );

		// Display settings.
		if ( isset( $_POST['nlf_faq_group_settings'] ) && is_array( $_POST['nlf_faq_group_settings'] ) ) {
			$raw_settings       = array_map( 'sanitize_text_field', wp_unslash( $_POST['nlf_faq_group_settings'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via array_map.
			$sanitized_settings = array(
				'accordion_mode'  => ! empty( $raw_settings['accordion_mode'] ),
				'initial_state'   => in_array( $raw_settings['initial_state'] ?? '', array( 'all_closed', 'first_open', 'custom' ), true ) ? $raw_settings['initial_state'] : 'all_closed',
				'animation_speed' => in_array( $raw_settings['animation_speed'] ?? '', array( 'fast', 'normal', 'slow' ), true ) ? $raw_settings['animation_speed'] : 'normal',
				'show_search'     => ! empty( $raw_settings['show_search'] ),
				'show_counter'    => ! empty( $raw_settings['show_counter'] ),
				'smooth_scroll'   => ! empty( $raw_settings['smooth_scroll'] ),
				'disable_schema'  => ! empty( $raw_settings['disable_schema'] ),
			);
			$update_data['display_settings'] = $sanitized_settings;
		}

		// Custom styles.
		$use_custom_style = ! empty( $_POST['nlf_faq_group_use_custom_style'] );
		$update_data['use_custom_style'] = $use_custom_style;

		// Content type (only relevant for new groups).
		$post_type = isset( $_POST['nlf_faq_group_type'] ) ? sanitize_key( $_POST['nlf_faq_group_type'] ) : 'faq';

		// If group_id is 0, create a new group first.
		if ( 0 === $group_id ) {
			if ( empty( $title ) ) {
				$title = __( 'Untitled FAQ Group', 'krslys-next-level-faq-accordion' );
				$update_data['title'] = $title;
			}

			$update_data['type'] = $post_type;

			$group_id = Groups_Repository::create_group( $update_data );

			if ( ! $group_id ) {
				wp_die( esc_html__( 'Failed to create FAQ group.', 'krslys-next-level-faq-accordion' ) );
			}

			$notice = 'created';
		} else {
			// Update existing group.
			Groups_Repository::update_group( $group_id, $update_data );
			$notice = 'saved';
		}

		// Handle custom style CSS generation.
		if ( $use_custom_style && isset( $_POST['nlf_faq_group_custom_styles'] ) ) {
			$custom_styles = Options::sanitize( wp_unslash( $_POST['nlf_faq_group_custom_styles'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via Options::sanitize().
			Groups_Repository::update_group( $group_id, array( 'custom_styles' => $custom_styles ) );
			Style_Generator::generate_and_save_for_group( $group_id, $custom_styles );
		} else {
			Groups_Repository::update_group( $group_id, array( 'custom_styles' => array() ) );
			Style_Generator::delete_group_css( $group_id );
		}

		// Save FAQ Items.
		$ids       = isset( $_POST['nlf_faq_group_item_id'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_item_id'] ) ) : array();
		$questions = isset( $_POST['nlf_faq_group_question'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_question'] ) ) : array();
		$answers   = isset( $_POST['nlf_faq_group_answer'] ) ? array_map( 'wp_kses_post', wp_unslash( (array) $_POST['nlf_faq_group_answer'] ) ) : array();
		$visible   = isset( $_POST['nlf_faq_group_visible'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_visible'] ) ) : array();
		$open      = isset( $_POST['nlf_faq_group_open'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_open'] ) ) : array();
		$highlight = isset( $_POST['nlf_faq_group_highlight'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_highlight'] ) ) : array();

		$keep_ids = array();
		$count    = max( count( $questions ), count( $answers ), count( $ids ) );

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

			$new_id     = Repository::save_item( $id, $group_id, $question, $answer, $status, $i, $initial_state, $is_highlight );
			$keep_ids[] = $new_id;
		}

		Repository::delete_all_except( $keep_ids, $group_id );

		Cache::invalidate_group( $group_id );

		// Redirect back to edit page with success notice.
		wp_safe_redirect( add_query_arg( array(
			'page'             => 'nlf-faq-group-edit',
			'id'               => $group_id,
			'type'             => $post_type,
			'nlf_group_notice' => $notice,
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ─────────────────────────────────────────────
	 * 7. handle_ajax_save_group() — AJAX save handler
	 * ───────────────────────────────────────────── */

	/**
	 * Handle AJAX save for FAQ group.
	 */
	public static function handle_ajax_save_group() {
		// Verify nonce.
		if ( ! isset( $_POST['nlf_faq_group_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nlf_faq_group_nonce'] ) ), 'nlf_faq_group_save' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'krslys-next-level-faq-accordion' ) ),
				403
			);
		}

		// Check capability.
		if ( ! Admin_Settings::current_user_can_manage() ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to edit this FAQ group.', 'krslys-next-level-faq-accordion' ) ),
				403
			);
		}

		// Get group ID.
		$group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;

		// Title is required.
		$title = isset( $_POST['nlf_group_title'] ) ? sanitize_text_field( wp_unslash( $_POST['nlf_group_title'] ) ) : '';
		if ( '' === $title ) {
			wp_send_json_error(
				array( 'message' => __( 'Title is required.', 'krslys-next-level-faq-accordion' ) ),
				400
			);
		}

		// Build update data.
		$update_data = array( 'title' => $title );

		// Theme.
		$theme_slug   = isset( $_POST['nlf_faq_group_theme'] ) ? sanitize_text_field( wp_unslash( $_POST['nlf_faq_group_theme'] ) ) : 'default';
		$theme_custom = isset( $_POST['nlf_faq_group_theme_custom'] ) ? array_map( 'sanitize_hex_color', wp_unslash( $_POST['nlf_faq_group_theme_custom'] ) ) : array();
		$update_data['theme_settings'] = array( 'theme' => $theme_slug, 'custom_colors' => $theme_custom );

		// Display settings.
		if ( isset( $_POST['nlf_faq_group_settings'] ) && is_array( $_POST['nlf_faq_group_settings'] ) ) {
			$raw_settings       = array_map( 'sanitize_text_field', wp_unslash( $_POST['nlf_faq_group_settings'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via array_map.
			$sanitized_settings = array(
				'accordion_mode'  => ! empty( $raw_settings['accordion_mode'] ),
				'initial_state'   => in_array( $raw_settings['initial_state'] ?? '', array( 'all_closed', 'first_open', 'custom' ), true ) ? $raw_settings['initial_state'] : 'all_closed',
				'animation_speed' => in_array( $raw_settings['animation_speed'] ?? '', array( 'fast', 'normal', 'slow' ), true ) ? $raw_settings['animation_speed'] : 'normal',
				'show_search'     => ! empty( $raw_settings['show_search'] ),
				'show_counter'    => ! empty( $raw_settings['show_counter'] ),
				'smooth_scroll'   => ! empty( $raw_settings['smooth_scroll'] ),
				'disable_schema'  => ! empty( $raw_settings['disable_schema'] ),
			);
			$update_data['display_settings'] = $sanitized_settings;
		}

		// Custom styles.
		$use_custom_style = ! empty( $_POST['nlf_faq_group_use_custom_style'] );
		$update_data['use_custom_style'] = $use_custom_style;

		// Content type (only relevant for new groups).
		$ajax_type = isset( $_POST['nlf_faq_group_type'] ) ? sanitize_key( $_POST['nlf_faq_group_type'] ) : 'faq';

		// Create or update group.
		if ( 0 === $group_id ) {
			if ( empty( $title ) ) {
				$update_data['title'] = __( 'Untitled FAQ Group', 'krslys-next-level-faq-accordion' );
			}

			$update_data['type'] = $ajax_type;

			$group_id = Groups_Repository::create_group( $update_data );

			if ( ! $group_id ) {
				wp_send_json_error(
					array( 'message' => __( 'Failed to create FAQ group.', 'krslys-next-level-faq-accordion' ) ),
					500
				);
			}
		} else {
			// Verify group exists.
			$existing = Groups_Repository::get_group_by_id( $group_id );

			if ( ! $existing ) {
				wp_send_json_error(
					array( 'message' => __( 'Invalid FAQ group.', 'krslys-next-level-faq-accordion' ) ),
					400
				);
			}

			Groups_Repository::update_group( $group_id, $update_data );
		}

		// Handle custom style CSS generation.
		if ( $use_custom_style && isset( $_POST['nlf_faq_group_custom_styles'] ) ) {
			$custom_styles = Options::sanitize( wp_unslash( $_POST['nlf_faq_group_custom_styles'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via Options::sanitize().
			Groups_Repository::update_group( $group_id, array( 'custom_styles' => $custom_styles ) );
			Style_Generator::generate_and_save_for_group( $group_id, $custom_styles );
		} else {
			Groups_Repository::update_group( $group_id, array( 'custom_styles' => array() ) );
			Style_Generator::delete_group_css( $group_id );
		}

		// Save FAQ Items.
		$ids       = isset( $_POST['nlf_faq_group_item_id'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_item_id'] ) ) : array();
		$questions = isset( $_POST['nlf_faq_group_question'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_question'] ) ) : array();
		$answers   = isset( $_POST['nlf_faq_group_answer'] ) ? array_map( 'wp_kses_post', wp_unslash( (array) $_POST['nlf_faq_group_answer'] ) ) : array();
		$visible   = isset( $_POST['nlf_faq_group_visible'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_visible'] ) ) : array();
		$open      = isset( $_POST['nlf_faq_group_open'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_open'] ) ) : array();
		$highlight = isset( $_POST['nlf_faq_group_highlight'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['nlf_faq_group_highlight'] ) ) : array();

		$keep_ids = array();
		$count    = max( count( $questions ), count( $answers ), count( $ids ) );

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

			$new_id     = Repository::save_item( $id, $group_id, $question, $answer, $status, $i, $initial_state, $is_highlight );
			$keep_ids[] = $new_id;
		}

		Repository::delete_all_except( $keep_ids, $group_id );

		Cache::invalidate_group( $group_id );

		// Build redirect URL.
		$redirect_url = add_query_arg( array(
			'page' => 'nlf-faq-group-edit',
			'id'   => $group_id,
			'type' => $ajax_type,
		), admin_url( 'admin.php' ) );

		wp_send_json_success( array(
			'message'      => __( 'FAQ group saved successfully!', 'krslys-next-level-faq-accordion' ),
			'group_id'     => $group_id,
			'redirect_url' => $redirect_url,
		) );
	}


	/* ─────────────────────────────────────────────
	 * 9. resolve_group_theme_options()
	 * ───────────────────────────────────────────── */

	/**
	 * Resolve a group's theme selection into a full CSS-compatible options array.
	 *
	 * Reads the group theme slug and optional custom color overrides from the
	 * groups table, then merges them into a complete options array that
	 * Style_Generator can use.
	 *
	 * @param int $group_id Group ID.
	 * @return array|null Full options array or null if no group theme is set.
	 */
	public static function resolve_group_theme_options( $group_id ) {
		$group_id = absint( $group_id );

		if ( ! $group_id ) {
			return null;
		}

		$group = Groups_Repository::get_group_by_id( $group_id );

		if ( ! $group ) {
			return null;
		}

		$theme_slug   = $group->theme_settings['theme'] ?? '';
		$theme_custom = $group->theme_settings['custom_colors'] ?? array();

		if ( empty( $theme_slug ) || 'default' === $theme_slug ) {
			// No group theme override -- fall back to global styles if no custom colors.
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

	/* ─────────────────────────────────────────────
	 * 10. get_default_settings()
	 * ───────────────────────────────────────────── */

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
			'disable_schema'  => false,
		);
	}

	/* ─────────────────────────────────────────────
	 * 11. HTML rendering methods
	 * ───────────────────────────────────────────── */

	/**
	 * Render Content tab (FAQ Items + Settings).
	 *
	 * @param int    $group_id Group ID.
	 * @param array  $items    FAQ items.
	 * @param string $type     Content type ('faq' or 'accordion').
	 */
	private static function render_content_tab( $group_id, $items, $type = 'faq' ) {
		$is_accordion = 'accordion' === $type;
		?>
		<?php if ( empty( $items ) ) : ?>
			<?php Admin_UI_Components::onboarding_card( $type ); ?>
		<?php endif; ?>

		<!-- FAQ Items Section -->
		<div class="nlf-section">
			<div class="nlf-section-header">
				<h3><?php echo $is_accordion ? esc_html__( 'Accordion Items', 'krslys-next-level-faq-accordion' ) : esc_html__( 'FAQ Items', 'krslys-next-level-faq-accordion' ); ?></h3>
				<p class="description">
					<?php echo $is_accordion ? esc_html__( 'Add titles and content for your accordion sections.', 'krslys-next-level-faq-accordion' ) : esc_html__( 'Add questions and answers that your visitors commonly ask.', 'krslys-next-level-faq-accordion' ); ?>
				</p>
			</div>
			<?php self::render_faq_items_table( $items, $type ); ?>
		</div>

		<?php
	}

	/**
	 * Render FAQ Items table.
	 *
	 * @param array  $items FAQ items.
	 * @param string $type  Content type ('faq' or 'accordion').
	 */
	private static function render_faq_items_table( $items, $type = 'faq' ) {
		$is_accordion = 'accordion' === $type;

		if ( empty( $items ) ) {
			// Empty state
			Admin_UI_Components::empty_state(
				array(
					'title'       => $is_accordion ? __( 'No items yet', 'krslys-next-level-faq-accordion' ) : __( 'No questions yet', 'krslys-next-level-faq-accordion' ),
					'description' => $is_accordion ? __( 'Add titles and content for your accordion sections.', 'krslys-next-level-faq-accordion' ) : __( 'Add questions and answers that your visitors commonly ask.', 'krslys-next-level-faq-accordion' ),
					'primary'     => array(
						'label' => $is_accordion ? __( 'Add Your First Item', 'krslys-next-level-faq-accordion' ) : __( 'Add Your First Question', 'krslys-next-level-faq-accordion' ),
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
					<th><?php echo $is_accordion ? esc_html__( 'Title & Content', 'krslys-next-level-faq-accordion' ) : esc_html__( 'Question & Answer', 'krslys-next-level-faq-accordion' ); ?></th>
					<th style="width:200px;"><?php esc_html_e( 'Options', 'krslys-next-level-faq-accordion' ); ?></th>
				</tr>
			</thead>
			<tbody id="nlf-faq-group-questions-body">
				<?php if ( ! empty( $items ) ) : ?>
					<?php foreach ( $items as $index => $item ) : ?>
						<tr class="nlf-faq-question-row">
							<td class="nlf-faq-sort-handle">&#8942;&#8942;</td>
							<td class="nlf-faq-content-cell">
								<input type="hidden" name="nlf_faq_group_item_id[]" value="<?php echo esc_attr( $item->id ); ?>" />
								<div class="nlf-faq-question-field">
									<label class="nlf-faq-field-label"><?php echo $is_accordion ? esc_html__( 'Title', 'krslys-next-level-faq-accordion' ) : esc_html__( 'Question', 'krslys-next-level-faq-accordion' ); ?></label>
									<input type="text" class="regular-text" name="nlf_faq_group_question[]" value="<?php echo esc_attr( $item->question ); ?>" placeholder="<?php echo $is_accordion ? esc_attr__( 'Enter your title...', 'krslys-next-level-faq-accordion' ) : esc_attr__( 'Enter your question...', 'krslys-next-level-faq-accordion' ); ?>" />
								</div>
								<div class="nlf-faq-answer-field">
									<label class="nlf-faq-field-label"><?php echo $is_accordion ? esc_html__( 'Content', 'krslys-next-level-faq-accordion' ) : esc_html__( 'Answer', 'krslys-next-level-faq-accordion' ); ?></label>
									<?php
									$editor_id = 'nlf_faq_group_answer_' . $index;
									wp_editor(
										$item->answer,
										$editor_id,
										array(
											'textarea_name'  => 'nlf_faq_group_answer[]',
											'textarea_class' => 'wp-editor-area large-text nlf-faq-group-answer-editor',
											'media_buttons'  => false,
											'teeny'          => true,
											'textarea_rows'  => 4,
										)
									);
									?>
								</div>
								<button type="button" class="nlf-faq-remove-row" aria-label="<?php esc_attr_e( 'Remove', 'krslys-next-level-faq-accordion' ); ?>" title="<?php esc_attr_e( 'Remove', 'krslys-next-level-faq-accordion' ); ?>">
									<span class="nlf-faq-remove-icon">&times;</span>
								</button>
							</td>
							<td class="nlf-faq-options-cell">
								<div class="nlf-faq-options-group">
									<p>
										<label>
											<input type="checkbox" name="nlf_faq_group_open[<?php echo esc_attr( $index ); ?>]" value="1" <?php checked( (int) $item->initial_state, 1 ); ?> />
											<?php esc_html_e( 'Open by default', 'krslys-next-level-faq-accordion' ); ?>
										</label>
									</p>
									<p>
										<label>
											<input type="checkbox" name="nlf_faq_group_visible[<?php echo esc_attr( $index ); ?>]" value="1" <?php checked( (int) $item->status, 1 ); ?> />
											<?php esc_html_e( 'Show', 'krslys-next-level-faq-accordion' ); ?>
										</label>
									</p>
									<p>
										<label>
											<input type="checkbox" name="nlf_faq_group_highlight[<?php echo esc_attr( $index ); ?>]" value="1" <?php checked( (int) $item->highlight, 1 ); ?> />
											<?php esc_html_e( 'Highlight', 'krslys-next-level-faq-accordion' ); ?>
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
							<?php echo $is_accordion ? esc_html__( 'Add Item', 'krslys-next-level-faq-accordion' ) : esc_html__( 'Add Question', 'krslys-next-level-faq-accordion' ); ?>
						</button>
					</td>
				</tr>
			</tfoot>
		</table>

		<script type="text/template" id="tmpl-nlf-faq-group-row">
			<tr class="nlf-faq-question-row">
				<td class="nlf-faq-sort-handle">&#8942;&#8942;</td>
				<td class="nlf-faq-content-cell">
					<input type="hidden" name="nlf_faq_group_item_id[]" value="" />
					<div class="nlf-faq-question-field">
						<label class="nlf-faq-field-label"><?php echo $is_accordion ? esc_html__( 'Title', 'krslys-next-level-faq-accordion' ) : esc_html__( 'Question', 'krslys-next-level-faq-accordion' ); ?></label>
						<input type="text" class="regular-text" name="nlf_faq_group_question[]" value="" placeholder="<?php echo $is_accordion ? esc_attr__( 'Enter your title...', 'krslys-next-level-faq-accordion' ) : esc_attr__( 'Enter your question...', 'krslys-next-level-faq-accordion' ); ?>" />
					</div>
					<div class="nlf-faq-answer-field">
						<label class="nlf-faq-field-label"><?php echo $is_accordion ? esc_html__( 'Content', 'krslys-next-level-faq-accordion' ) : esc_html__( 'Answer', 'krslys-next-level-faq-accordion' ); ?></label>
						<textarea id="nlf-faq-group-answer-{{index}}" name="nlf_faq_group_answer[]" rows="4" class="large-text nlf-faq-group-answer-editor" placeholder="<?php echo $is_accordion ? esc_attr__( 'Enter your content...', 'krslys-next-level-faq-accordion' ) : esc_attr__( 'Enter your answer...', 'krslys-next-level-faq-accordion' ); ?>"></textarea>
					</div>
					<button type="button" class="nlf-faq-remove-row" aria-label="<?php esc_attr_e( 'Remove', 'krslys-next-level-faq-accordion' ); ?>" title="<?php esc_attr_e( 'Remove', 'krslys-next-level-faq-accordion' ); ?>">
						<span class="nlf-faq-remove-icon">&times;</span>
					</button>
				</td>
				<td class="nlf-faq-options-cell">
					<div class="nlf-faq-options-group">
						<p>
							<label>
								<input type="checkbox" name="nlf_faq_group_open[{{index}}]" value="1" checked="checked" />
								<?php esc_html_e( 'Open by default', 'krslys-next-level-faq-accordion' ); ?>
							</label>
						</p>
						<p>
							<label>
								<input type="checkbox" name="nlf_faq_group_visible[{{index}}]" value="1" checked="checked" />
								<?php esc_html_e( 'Show', 'krslys-next-level-faq-accordion' ); ?>
							</label>
						</p>
						<p>
							<label>
								<input type="checkbox" name="nlf_faq_group_highlight[{{index}}]" value="1" />
								<?php esc_html_e( 'Highlight', 'krslys-next-level-faq-accordion' ); ?>
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
	 * @param int   $group_id Group ID.
	 * @param array $items    FAQ items (for preview).
	 */
	private static function render_appearance_tab( $group_id, $items ) {
		$group         = $group_id ? Groups_Repository::get_group_by_id( $group_id ) : null;
		$theme_slug    = $group->theme_settings['theme'] ?? 'default';
		$theme_custom  = $group->theme_settings['custom_colors'] ?? array();
		?>
		<div class="nlf-appearance-wrapper">
			<div class="nlf-appearance-layout">
				<div class="nlf-appearance-controls">
					<!-- Quick Style Section -->
					<div class="nlf-section">
						<div class="nlf-section-header">
							<h3><?php esc_html_e( 'Theme Presets', 'krslys-next-level-faq-accordion' ); ?></h3>
							<p class="description">
								<?php esc_html_e( 'Choose a pre-designed theme to quickly style your FAQs.', 'krslys-next-level-faq-accordion' ); ?>
							</p>
						</div>
						<?php self::render_theme_selector( $theme_slug, $theme_custom ); ?>
					</div>

					<!-- Advanced Styles Section -->
					<div class="nlf-section nlf-section-bordered">
						<div class="nlf-section-header">
							<h3><?php esc_html_e( 'Advanced Style Overrides', 'krslys-next-level-faq-accordion' ); ?></h3>
							<p class="description">
								<?php esc_html_e( 'Fine-tune every detail or override global styles for this group.', 'krslys-next-level-faq-accordion' ); ?>
							</p>
						</div>
						<?php self::render_custom_styles(); ?>
					</div>

					<div class="nlf-reset-row">
						<button type="button" class="button button-secondary" data-reset="theme">
							<?php esc_html_e( 'Reset Theme', 'krslys-next-level-faq-accordion' ); ?>
						</button>
						<button type="button" class="button button-secondary" data-reset="styles">
							<?php esc_html_e( 'Reset Styles', 'krslys-next-level-faq-accordion' ); ?>
						</button>
					</div>
				</div>

				<div class="nlf-appearance-preview">
					<div class="nlf-preview-mini-header">
						<h3><?php esc_html_e( 'Live Preview', 'krslys-next-level-faq-accordion' ); ?></h3>
						<div class="nlf-preview-mini-actions">
							<label class="nlf-preview-auto nlf-preview-auto--small">
								<input type="checkbox" class="nlf-preview-auto-toggle" data-preview-auto="appearance" checked>
								<span><?php esc_html_e( 'Auto refresh', 'krslys-next-level-faq-accordion' ); ?></span>
							</label>
							<button type="button" class="button button-small" data-refresh-preview="appearance">
								<span class="dashicons dashicons-update" aria-hidden="true"></span>
								<?php esc_html_e( 'Refresh', 'krslys-next-level-faq-accordion' ); ?>
							</button>
						</div>
					</div>
					<p class="description"><?php esc_html_e( 'Changes update instantly as you tweak styles.', 'krslys-next-level-faq-accordion' ); ?></p>
					<div class="nlf-preview-empty-state nlf-preview-empty-state--mini"<?php echo ! empty( $items ) ? ' style="display:none"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static safe strings. ?>>
						<p><?php esc_html_e( 'Add at least one question to see the live preview.', 'krslys-next-level-faq-accordion' ); ?></p>
						<button type="button" class="button button-secondary" data-switch-tab="content">
							<?php esc_html_e( 'Add questions first', 'krslys-next-level-faq-accordion' ); ?> &rarr;
						</button>
					</div>
					<?php Admin_UI_Components::preview_container( $group_id, 'appearance' ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render theme selector.
	 *
	 * @param string $current_theme Selected theme slug.
	 * @param array  $theme_custom  Custom color overrides keyed by primary/secondary/accent/background.
	 */
	private static function render_theme_selector( $current_theme = 'default', $theme_custom = array() ) {
		$themes = self::get_theme_presets();

		if ( empty( $current_theme ) || ! isset( $themes[ $current_theme ] ) ) {
			$current_theme = 'default';
		}

		$layout_labels = array(
			'flat'     => __( 'Flat', 'krslys-next-level-faq-accordion' ),
			'cards'    => __( 'Cards', 'krslys-next-level-faq-accordion' ),
			'bordered' => __( 'Bordered', 'krslys-next-level-faq-accordion' ),
			'clean'    => __( 'Clean', 'krslys-next-level-faq-accordion' ),
			'striped'  => __( 'Striped', 'krslys-next-level-faq-accordion' ),
		);
		?>
		<div class="nlf-theme-selector" role="radiogroup" aria-label="<?php esc_attr_e( 'Choose a theme preset', 'krslys-next-level-faq-accordion' ); ?>" data-default-theme="default">
			<?php foreach ( $themes as $theme_id => $theme_data ) :
				$is_active = ( $theme_id === $current_theme );
				$layout = $theme_data['values']['layout'] ?? 'flat';
				$is_cards = 'cards' === $layout;
				$radius = ( $theme_data['values']['container_border_radius'] ?? 8 ) . 'px';
				$has_shadow = ! empty( $theme_data['values']['shadow'] ) && false !== $theme_data['values']['shadow'];
				$preview_shadow = $has_shadow ? '0 2px 8px rgba(0,0,0,0.08)' : 'none';
			?>
				<div class="nlf-theme-option <?php echo esc_attr( $is_active ? 'is-active' : '' ); ?>" data-theme="<?php echo esc_attr( $theme_id ); ?>">
					<input type="radio" name="nlf_faq_group_theme" value="<?php echo esc_attr( $theme_id ); ?>" id="theme_<?php echo esc_attr( $theme_id ); ?>" <?php checked( $theme_id, $current_theme ); ?> />
					<label for="theme_<?php echo esc_attr( $theme_id ); ?>">
						<div class="nlf-theme-preview nlf-theme-preview--<?php echo esc_attr( $layout ); ?>" style="
							background: <?php echo esc_attr( $is_cards ? 'transparent' : $theme_data['background'] ); ?>;
							border-color: <?php echo esc_attr( $is_cards ? 'transparent' : $theme_data['border'] ); ?>;
							border-radius: <?php echo esc_attr( $radius ); ?>;
							box-shadow: <?php echo esc_attr( $is_cards ? 'none' : $preview_shadow ); ?>;
						">
							<div class="nlf-theme-preview-item" style="
								background: <?php echo esc_attr( $is_cards ? $theme_data['background'] : 'transparent' ); ?>;
								border: <?php echo esc_attr( $is_cards ? '1px solid ' . $theme_data['border'] : 'none' ); ?>;
								border-radius: <?php echo esc_attr( $is_cards ? $radius : '0' ); ?>;
								border-bottom: <?php echo esc_attr( ! $is_cards ? '1px solid ' . $theme_data['border'] : 'none' ); ?>;
								box-shadow: <?php echo esc_attr( $is_cards ? $preview_shadow : 'none' ); ?>;
								padding: 8px <?php echo esc_attr( $is_cards ? '10px' : '0' ); ?>;
							">
								<div class="nlf-theme-preview-question" style="color: <?php echo esc_attr( $theme_data['question'] ); ?>;">
									<?php esc_html_e( 'Sample Question?', 'krslys-next-level-faq-accordion' ); ?>
								</div>
								<div class="nlf-theme-preview-answer" style="color: <?php echo esc_attr( $theme_data['answer'] ); ?>;">
									<?php esc_html_e( 'Preview answer text...', 'krslys-next-level-faq-accordion' ); ?>
								</div>
							</div>
							<div class="nlf-theme-preview-item nlf-theme-preview-item--collapsed" style="
								background: <?php echo esc_attr( $is_cards ? $theme_data['background'] : 'transparent' ); ?>;
								border: <?php echo esc_attr( $is_cards ? '1px solid ' . $theme_data['border'] : 'none' ); ?>;
								border-radius: <?php echo esc_attr( $is_cards ? $radius : '0' ); ?>;
								box-shadow: <?php echo esc_attr( $is_cards ? '0 1px 3px rgba(0,0,0,0.04)' : 'none' ); ?>;
								padding: 8px <?php echo esc_attr( $is_cards ? '10px' : '0' ); ?>;
							">
								<div class="nlf-theme-preview-question" style="color: <?php echo esc_attr( $theme_data['question'] ); ?>; opacity: 0.7;">
									<?php esc_html_e( 'Another Question?', 'krslys-next-level-faq-accordion' ); ?>
								</div>
							</div>
							<div class="nlf-theme-preview-accent" style="background: <?php echo esc_attr( $theme_data['accent'] ); ?>;"></div>
						</div>
						<div class="nlf-theme-info">
							<div class="nlf-theme-name"><?php echo esc_html( $theme_data['name'] ); ?></div>
							<p><?php echo esc_html( $theme_data['description'] ); ?></p>
							<span class="nlf-theme-layout-tag"><?php echo esc_html( $layout_labels[ $layout ] ?? $layout ); ?></span>
						</div>
						<span class="nlf-theme-badge" aria-hidden="true">
							<?php esc_html_e( 'Applied', 'krslys-next-level-faq-accordion' ); ?>
						</span>
					</label>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="nlf-theme-customizer" aria-live="polite">
			<h4><?php esc_html_e( 'Customize Colors', 'krslys-next-level-faq-accordion' ); ?></h4>
			<p class="description">
				<?php esc_html_e( 'Override theme colors with your own custom values.', 'krslys-next-level-faq-accordion' ); ?>
			</p>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="theme_custom_primary"><?php esc_html_e( 'Primary Color', 'krslys-next-level-faq-accordion' ); ?></label>
					</th>
					<td>
						<input type="text" id="theme_custom_primary" name="nlf_faq_group_theme_custom[primary]" value="<?php echo esc_attr( $theme_custom['primary'] ?? '' ); ?>" class="nlf-color-picker nlf-theme-color" data-color-key="primary" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="theme_custom_secondary"><?php esc_html_e( 'Secondary Color', 'krslys-next-level-faq-accordion' ); ?></label>
					</th>
					<td>
						<input type="text" id="theme_custom_secondary" name="nlf_faq_group_theme_custom[secondary]" value="<?php echo esc_attr( $theme_custom['secondary'] ?? '' ); ?>" class="nlf-color-picker nlf-theme-color" data-color-key="secondary" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="theme_custom_accent"><?php esc_html_e( 'Accent Color', 'krslys-next-level-faq-accordion' ); ?></label>
					</th>
					<td>
						<input type="text" id="theme_custom_accent" name="nlf_faq_group_theme_custom[accent]" value="<?php echo esc_attr( $theme_custom['accent'] ?? '' ); ?>" class="nlf-color-picker nlf-theme-color" data-color-key="accent" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="theme_custom_background"><?php esc_html_e( 'Background Color', 'krslys-next-level-faq-accordion' ); ?></label>
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
	private static function render_settings_fields( $type = 'faq' ) {
		$is_accordion = 'accordion' === $type;
		?>
		<div class="nlf-settings-wrapper">
			<h4 class="nlf-subsection-title"><?php esc_html_e( 'How should users interact?', 'krslys-next-level-faq-accordion' ); ?></h4>
			<table class="form-table nlf-settings-table">
				<tr>
					<th scope="row">
						<label for="setting_accordion_mode">
							<?php esc_html_e( 'Accordion Mode', 'krslys-next-level-faq-accordion' ); ?>
							<button type="button" class="nlf-help-trigger" aria-label="<?php esc_attr_e( 'Learn more', 'krslys-next-level-faq-accordion' ); ?>" data-tooltip="accordion-help">
								<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
							</button>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="setting_accordion_mode" name="nlf_faq_group_settings[accordion_mode]" value="1" />
							<?php esc_html_e( 'Only allow one item to be open at a time', 'krslys-next-level-faq-accordion' ); ?>
						</label>
					<p class="nlf-help-text" id="accordion-help" hidden>
							<?php esc_html_e( 'When enabled, opening one item automatically closes all others. Perfect for keeping your FAQ section compact.', 'krslys-next-level-faq-accordion' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="setting_initial_state">
							<?php esc_html_e( 'Initial State', 'krslys-next-level-faq-accordion' ); ?>
							<button type="button" class="nlf-help-trigger" aria-label="<?php esc_attr_e( 'Learn more', 'krslys-next-level-faq-accordion' ); ?>" data-tooltip="initial-help">
								<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
							</button>
						</label>
					</th>
					<td>
						<select id="setting_initial_state" name="nlf_faq_group_settings[initial_state]">
							<option value="all_closed"><?php esc_html_e( 'All Closed', 'krslys-next-level-faq-accordion' ); ?></option>
							<option value="first_open"><?php esc_html_e( 'First Item Open', 'krslys-next-level-faq-accordion' ); ?></option>
							<option value="custom"><?php esc_html_e( 'Custom (Use item settings)', 'krslys-next-level-faq-accordion' ); ?></option>
						</select>
					<p class="nlf-help-text" id="initial-help" hidden>
							<?php esc_html_e( 'Choose how items should appear when the page loads. "Custom" uses the "Open by default" setting for each item.', 'krslys-next-level-faq-accordion' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="setting_animation_speed">
							<?php esc_html_e( 'Animation Speed', 'krslys-next-level-faq-accordion' ); ?>
							<button type="button" class="nlf-help-trigger" aria-label="<?php esc_attr_e( 'Learn more', 'krslys-next-level-faq-accordion' ); ?>" data-tooltip="animation-help">
								<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
							</button>
						</label>
					</th>
					<td>
						<select id="setting_animation_speed" name="nlf_faq_group_settings[animation_speed]">
							<option value="fast"><?php esc_html_e( 'Fast (150ms)', 'krslys-next-level-faq-accordion' ); ?></option>
							<option value="normal"><?php esc_html_e( 'Normal (300ms)', 'krslys-next-level-faq-accordion' ); ?></option>
							<option value="slow"><?php esc_html_e( 'Slow (500ms)', 'krslys-next-level-faq-accordion' ); ?></option>
						</select>
					<p class="nlf-help-text" id="animation-help" hidden>
							<?php esc_html_e( 'Controls how quickly items expand and collapse. Normal works well for most sites.', 'krslys-next-level-faq-accordion' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h4 class="nlf-subsection-title"><?php esc_html_e( 'What should users see?', 'krslys-next-level-faq-accordion' ); ?></h4>
			<table class="form-table nlf-settings-table">
				<tr>
					<th scope="row">
						<label for="setting_show_search">
							<?php esc_html_e( 'Search Box', 'krslys-next-level-faq-accordion' ); ?>
							<button type="button" class="nlf-help-trigger" aria-label="<?php esc_attr_e( 'Learn more', 'krslys-next-level-faq-accordion' ); ?>" data-tooltip="search-help">
								<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
							</button>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="setting_show_search" name="nlf_faq_group_settings[show_search]" value="1" />
							<?php esc_html_e( 'Show search box above FAQ items', 'krslys-next-level-faq-accordion' ); ?>
						</label>
					<p class="nlf-help-text" id="search-help" hidden>
							<?php esc_html_e( 'Adds a live search box that filters questions and answers as visitors type.', 'krslys-next-level-faq-accordion' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="setting_show_counter">
							<?php esc_html_e( 'Item Counter', 'krslys-next-level-faq-accordion' ); ?>
							<button type="button" class="nlf-help-trigger" aria-label="<?php esc_attr_e( 'Learn more', 'krslys-next-level-faq-accordion' ); ?>" data-tooltip="counter-help">
								<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
							</button>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="setting_show_counter" name="nlf_faq_group_settings[show_counter]" value="1" />
							<?php esc_html_e( 'Display item numbers (e.g., 1., 2., 3.)', 'krslys-next-level-faq-accordion' ); ?>
						</label>
					<p class="nlf-help-text" id="counter-help" hidden>
							<?php esc_html_e( 'Shows numbered labels before each question for easy reference.', 'krslys-next-level-faq-accordion' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="setting_smooth_scroll">
							<?php esc_html_e( 'Smooth Scroll', 'krslys-next-level-faq-accordion' ); ?>
							<button type="button" class="nlf-help-trigger" aria-label="<?php esc_attr_e( 'Learn more', 'krslys-next-level-faq-accordion' ); ?>" data-tooltip="scroll-help">
								<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
							</button>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="setting_smooth_scroll" name="nlf_faq_group_settings[smooth_scroll]" value="1" />
							<?php esc_html_e( 'Scroll to item when opened via URL hash', 'krslys-next-level-faq-accordion' ); ?>
						</label>
					<p class="nlf-help-text" id="scroll-help" hidden>
							<?php esc_html_e( 'Smoothly scrolls opened items into view, helpful when linking directly to specific questions.', 'krslys-next-level-faq-accordion' ); ?>
						</p>
					</td>
				</tr>
				<?php if ( ! $is_accordion ) : ?>
				<tr>
					<th scope="row">
						<label for="setting_disable_schema">
							<?php esc_html_e( 'Schema Markup', 'krslys-next-level-faq-accordion' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="setting_disable_schema" name="nlf_faq_group_settings[disable_schema]" value="1" />
							<?php esc_html_e( 'Disable FAQPage schema markup for this group', 'krslys-next-level-faq-accordion' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Excludes this group from schema.org/FAQPage structured data output.', 'krslys-next-level-faq-accordion' ); ?>
						</p>
					</td>
				</tr>
			<?php endif; ?>
			</table>
		</div>
		<?php
	}

	/**
	 * Render custom styles section.
	 *
	 * @param bool  $use_custom_style Whether to use custom styles.
	 * @param array $custom_styles    Custom style values.
	 */
	private static function render_custom_styles() {
		$default_styles = Options::get_defaults();
		unset( $default_styles['preset'] );
		?>
		<div class="nlf-style-wrapper">
			<div class="nlf-style-toggle">
				<label>
					<input type="checkbox" name="nlf_faq_group_use_custom_style" value="1" id="nlf-use-custom-style-toggle" />
					<strong><?php esc_html_e( 'Use custom styles for this group', 'krslys-next-level-faq-accordion' ); ?></strong>
				</label>
				<p class="description">
					<?php esc_html_e( 'Enable this to override global styles with group-specific styling.', 'krslys-next-level-faq-accordion' ); ?>
				</p>
			</div>

			<div class="nlf-custom-style-fields" style="display: none;" data-default-styles="<?php echo esc_attr( wp_json_encode( $default_styles ) ); ?>">
				<h3><?php esc_html_e( 'Container', 'krslys-next-level-faq-accordion' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="custom_container_background"><?php esc_html_e( 'Background', 'krslys-next-level-faq-accordion' ); ?></label>
						</th>
						<td>
							<input type="text" id="custom_container_background" name="nlf_faq_group_custom_styles[container_background]" value="" class="nlf-color-picker" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_container_border_color"><?php esc_html_e( 'Border Color', 'krslys-next-level-faq-accordion' ); ?></label>
						</th>
						<td>
							<input type="text" id="custom_container_border_color" name="nlf_faq_group_custom_styles[container_border_color]" value="" class="nlf-color-picker" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_container_border_radius"><?php esc_html_e( 'Border Radius (px)', 'krslys-next-level-faq-accordion' ); ?></label>
						</th>
						<td>
							<input type="number" min="0" id="custom_container_border_radius" name="nlf_faq_group_custom_styles[container_border_radius]" value="" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_container_padding"><?php esc_html_e( 'Padding (px)', 'krslys-next-level-faq-accordion' ); ?></label>
						</th>
						<td>
							<input type="number" min="0" id="custom_container_padding" name="nlf_faq_group_custom_styles[container_padding]" value="" />
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Question', 'krslys-next-level-faq-accordion' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="custom_question_color"><?php esc_html_e( 'Color', 'krslys-next-level-faq-accordion' ); ?></label>
						</th>
						<td>
							<input type="text" id="custom_question_color" name="nlf_faq_group_custom_styles[question_color]" value="" class="nlf-color-picker" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_question_font_size"><?php esc_html_e( 'Font Size (px)', 'krslys-next-level-faq-accordion' ); ?></label>
						</th>
						<td>
							<input type="number" min="10" id="custom_question_font_size" name="nlf_faq_group_custom_styles[question_font_size]" value="" />
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Answer', 'krslys-next-level-faq-accordion' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="custom_answer_color"><?php esc_html_e( 'Color', 'krslys-next-level-faq-accordion' ); ?></label>
						</th>
						<td>
							<input type="text" id="custom_answer_color" name="nlf_faq_group_custom_styles[answer_color]" value="" class="nlf-color-picker" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_answer_font_size"><?php esc_html_e( 'Font Size (px)', 'krslys-next-level-faq-accordion' ); ?></label>
						</th>
						<td>
							<input type="number" min="10" id="custom_answer_font_size" name="nlf_faq_group_custom_styles[answer_font_size]" value="" />
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Accent & Animation', 'krslys-next-level-faq-accordion' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="custom_accent_color"><?php esc_html_e( 'Accent Color', 'krslys-next-level-faq-accordion' ); ?></label>
						</th>
						<td>
							<input type="text" id="custom_accent_color" name="nlf_faq_group_custom_styles[accent_color]" value="" class="nlf-color-picker" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_icon_style"><?php esc_html_e( 'Icon Style', 'krslys-next-level-faq-accordion' ); ?></label>
						</th>
						<td>
							<select id="custom_icon_style" name="nlf_faq_group_custom_styles[icon_style]">
								<option value="plus_minus"><?php esc_html_e( 'Plus / Minus', 'krslys-next-level-faq-accordion' ); ?></option>
								<option value="chevron"><?php esc_html_e( 'Chevron', 'krslys-next-level-faq-accordion' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="custom_animation"><?php esc_html_e( 'Animation', 'krslys-next-level-faq-accordion' ); ?></label>
						</th>
						<td>
							<select id="custom_animation" name="nlf_faq_group_custom_styles[animation]">
								<option value="slide"><?php esc_html_e( 'Slide', 'krslys-next-level-faq-accordion' ); ?></option>
								<option value="fade"><?php esc_html_e( 'Fade', 'krslys-next-level-faq-accordion' ); ?></option>
								<option value="none"><?php esc_html_e( 'None', 'krslys-next-level-faq-accordion' ); ?></option>
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
	 * @param int   $group_id Group ID.
	 * @param array $items    FAQ items.
	 */
	private static function render_preview_tab( $group_id, $items ) {
		?>
		<div class="nlf-preview-wrapper">
		<!-- Empty state: hidden by JS once the first question is added. -->
		<div class="nlf-preview-empty-state"<?php echo ! empty( $items ) ? ' style="display:none"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static safe strings. ?>>
			<div class="nlf-empty-icon">
				<span class="dashicons dashicons-visibility"></span>
			</div>
			<h3><?php esc_html_e( 'Preview will appear after you add items', 'krslys-next-level-faq-accordion' ); ?></h3>
			<p><?php esc_html_e( 'Add at least one question in the Content tab, then return here to see how it looks.', 'krslys-next-level-faq-accordion' ); ?></p>
			<button type="button" class="button button-primary" data-switch-tab="content">
				<?php esc_html_e( 'Go to Content Tab', 'krslys-next-level-faq-accordion' ); ?> &rarr;
			</button>
		</div>
		<?php $preview_hidden = empty( $items ) ? ' style="display:none"' : ''; ?>
			<div class="nlf-preview-controls"<?php echo $preview_hidden; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<div class="nlf-preview-device-toggle" role="radiogroup" aria-label="<?php esc_attr_e( 'Preview device', 'krslys-next-level-faq-accordion' ); ?>">
					<button type="button" class="nlf-device-btn active" data-device="desktop" aria-label="<?php esc_attr_e( 'Desktop view', 'krslys-next-level-faq-accordion' ); ?>">
						<span class="dashicons dashicons-desktop" aria-hidden="true"></span>
					</button>
					<button type="button" class="nlf-device-btn" data-device="tablet" aria-label="<?php esc_attr_e( 'Tablet view', 'krslys-next-level-faq-accordion' ); ?>">
						<span class="dashicons dashicons-tablet" aria-hidden="true"></span>
					</button>
					<button type="button" class="nlf-device-btn" data-device="mobile" aria-label="<?php esc_attr_e( 'Mobile view', 'krslys-next-level-faq-accordion' ); ?>">
						<span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
					</button>
				</div>
				<div class="nlf-preview-controls-right">
					<label class="nlf-preview-auto">
						<input type="checkbox" class="nlf-preview-auto-toggle" data-preview-auto="main" checked>
						<span><?php esc_html_e( 'Auto refresh', 'krslys-next-level-faq-accordion' ); ?></span>
					</label>
					<button type="button" class="button nlf-refresh-preview" data-refresh-preview="main">
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
						<?php esc_html_e( 'Refresh Preview', 'krslys-next-level-faq-accordion' ); ?>
					</button>
				</div>
			</div>

			<div class="nlf-preview-notice"<?php echo $preview_hidden; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<span class="dashicons dashicons-info" aria-hidden="true"></span>
				<span><?php esc_html_e( 'Save the group to see all changes reflected in the preview.', 'krslys-next-level-faq-accordion' ); ?></span>
			</div>

			<div class="nlf-preview-viewport"<?php echo $preview_hidden; // phpcs:ignore WordPress.Security.EscapeOutput ?> data-device="desktop">
				<?php Admin_UI_Components::preview_container( $group_id, 'main' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Build a JS-ready presets map with pre-computed inline styles.
	 *
	 * Each entry contains the CSS custom-property string, layout slug, and
	 * icon style so the client can render a preview without an AJAX round-trip.
	 *
	 * @return array<string, array{inlineStyle: string, layout: string, iconStyle: string}>
	 */
	private static function get_presets_for_js() {
		$result = array();

		foreach ( self::get_theme_presets() as $slug => $data ) {
			$values          = isset( $data['values'] ) ? $data['values'] : array();
			$result[ $slug ] = array(
				'inlineStyle' => Style_Generator::build_inline_style( $values ),
				'layout'      => $values['layout'] ?? 'flat',
				'iconStyle'   => $values['icon_style'] ?? 'plus_minus',
			);
		}

		return $result;
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
				'name'        => __( 'Default', 'krslys-next-level-faq-accordion' ),
				'description' => __( 'Refined baseline with soft shadows. Fits any site.', 'krslys-next-level-faq-accordion' ),
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
				'name'        => __( 'Modern', 'krslys-next-level-faq-accordion' ),
				'description' => __( 'Floating cards with indigo accent. Clean and airy.', 'krslys-next-level-faq-accordion' ),
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
				'name'        => __( 'Elegant', 'krslys-next-level-faq-accordion' ),
				'description' => __( 'Warm tones with connected bordered items.', 'krslys-next-level-faq-accordion' ),
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
				'name'        => __( 'Minimal', 'krslys-next-level-faq-accordion' ),
				'description' => __( 'Stripped to essentials. Content speaks for itself.', 'krslys-next-level-faq-accordion' ),
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
				'name'        => __( 'Bold', 'krslys-next-level-faq-accordion' ),
				'description' => __( 'Strong cards with emerald accent. High visual impact.', 'krslys-next-level-faq-accordion' ),
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
				'name'        => __( 'Professional', 'krslys-next-level-faq-accordion' ),
				'description' => __( 'Structured alternating rows. Enterprise-ready.', 'krslys-next-level-faq-accordion' ),
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

	/* ─────────────────────────────────────────────
	 * 12. render_how_to_use_sidebar()
	 * ───────────────────────────────────────────── */

	/**
	 * Render the "How To Use" sidebar content.
	 *
	 * Shows shortcode, PHP template tag, and Gutenberg block usage
	 * with click-to-copy interaction.
	 *
	 * @param int $group_id Group ID.
	 */
	public static function render_how_to_use_sidebar( $group_id ) {
		$group_id       = (int) $group_id;
		$shortcode_text = '[krslys_nlfa group="' . $group_id . '"]';
		$php_text       = "<?php echo do_shortcode( '" . $shortcode_text . "' ); ?>";

		// Inline SVG icons.
		$icon_copy  = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
		$icon_check = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
		$icon_block = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>';
		?>
		<div class="nlf-how-to-use">

			<button type="button" class="nlf-htu-snippet" data-copy-text="<?php echo esc_attr( $shortcode_text ); ?>">
				<span class="nlf-htu-snippet__label"><?php esc_html_e( 'Shortcode', 'krslys-next-level-faq-accordion' ); ?></span>
				<span class="nlf-htu-snippet__row">
					<code class="nlf-htu-snippet__code"><?php echo esc_html( $shortcode_text ); ?></code>
					<span class="nlf-htu-snippet__icon nlf-htu-snippet__icon--copy"><?php echo $icon_copy; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
					<span class="nlf-htu-snippet__icon nlf-htu-snippet__icon--ok"><?php echo $icon_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
				</span>
				<span class="nlf-htu-snippet__toast"><?php esc_html_e( 'Copied!', 'krslys-next-level-faq-accordion' ); ?></span>
			</button>

			<button type="button" class="nlf-htu-snippet" data-copy-text="<?php echo esc_attr( $php_text ); ?>">
				<span class="nlf-htu-snippet__label"><?php esc_html_e( 'PHP Template', 'krslys-next-level-faq-accordion' ); ?></span>
				<span class="nlf-htu-snippet__row">
					<code class="nlf-htu-snippet__code"><?php echo esc_html( $php_text ); ?></code>
					<span class="nlf-htu-snippet__icon nlf-htu-snippet__icon--copy"><?php echo $icon_copy; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
					<span class="nlf-htu-snippet__icon nlf-htu-snippet__icon--ok"><?php echo $icon_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
				</span>
				<span class="nlf-htu-snippet__toast"><?php esc_html_e( 'Copied!', 'krslys-next-level-faq-accordion' ); ?></span>
			</button>

			<div class="nlf-htu-block-hint">
				<span class="nlf-htu-block-hint__icon"><?php echo $icon_block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
				<span class="nlf-htu-block-hint__text">
					<?php esc_html_e( 'Also available as a Gutenberg block.', 'krslys-next-level-faq-accordion' ); ?>
				</span>
			</div>

		</div>
		<?php
	}
}
