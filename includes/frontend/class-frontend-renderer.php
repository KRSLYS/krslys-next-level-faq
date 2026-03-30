<?php
/**
 * Front-end rendering and assets.
 *
 * @package Krslys\NextLevelFaq
 */

namespace Krslys\NextLevelFaq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Front-end rendering and assets.
 *
 * SECURITY FEATURES:
 * - All shortcode attributes sanitized.
 * - All output properly escaped.
 * - No direct user input accepted without validation.
 */
class Frontend_Renderer {

	/**
	 * Bootstrap all frontend hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
		add_action( 'init', array( __CLASS__, 'register_tracking_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
	}

	/**
	 * Register shortcodes.
	 */
	public static function register_shortcodes() {
		add_shortcode( 'krslys_nlf', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Enqueue front-end styles and scripts.
	 *
	 * SECURITY: Uses esc_url_raw() for CSS URL.
	 */
	public static function enqueue_styles() {
		// Enqueue global CSS
		$css_path = Style_Generator::get_css_file_path();
		$css_url  = Style_Generator::get_css_file_url();

		$uploads = wp_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? trailingslashit( $uploads['baseurl'] ) : '';

		if ( $css_url && $css_path && file_exists( $css_path ) && $baseurl && 0 === strpos( $css_url, $baseurl ) ) {
			wp_enqueue_style(
				'nlf-faq-generated',
				esc_url_raw( $css_url ),
				array(),
				filemtime( $css_path )
			);
		}

		// Enqueue group-specific CSS if needed (will be done per group in shortcode)
		wp_enqueue_script(
			'nlf-faq-frontend',
			nlf_asset_url( 'assets/js/frontend-faq.js' ),
			array(),
			NLF_FAQ_VERSION,
			true
		);

		wp_localize_script(
			'nlf-faq-frontend',
			'nlfFaqData',
			array(
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'nlf_faq_track' ),
				'tracking'  => true,
			)
		);
	}
	/**
	 * Register AJAX routes for analytics tracking.
	 */
	public static function register_tracking_routes() {
		add_action( 'wp_ajax_nlf_faq_track', array( __CLASS__, 'track_interaction' ) );
		add_action( 'wp_ajax_nopriv_nlf_faq_track', array( __CLASS__, 'track_interaction' ) );
	}

	/**
	 * Handle analytics tracking requests.
	 */
	public static function track_interaction() {
		check_ajax_referer( 'nlf_faq_track', 'nonce' );

		$group_id    = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
		$question_id = isset( $_POST['question_id'] ) ? absint( $_POST['question_id'] ) : 0;
		$state       = isset( $_POST['state'] ) ? sanitize_key( $_POST['state'] ) : '';

		if ( $group_id <= 0 || $question_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tracking payload.', 'next-level-faq' ) ), 400 );
		}

		// Verify the group exists in the custom table.
		$group = \Krslys\NextLevelFaq\Groups_Repository::get_group_by_id( $group_id );
		if ( ! $group ) {
			wp_send_json_error( array( 'message' => __( 'Group not found.', 'next-level-faq' ) ), 404 );
		}

		// Track stats in Settings_Repository or group metadata
		$stats_key = 'group_' . $group_id . '_stats';
		$stats = Settings_Repository::get_setting( $stats_key, array() );

		if ( ! isset( $stats[ $question_id ] ) ) {
			$stats[ $question_id ] = array(
				'opens'  => 0,
				'closes' => 0,
			);
		}

		if ( 'open' === $state ) {
			$stats[ $question_id ]['opens'] ++;
		} elseif ( 'close' === $state ) {
			$stats[ $question_id ]['closes'] ++;
		}

		Settings_Repository::update_setting( $stats_key, $stats );

		/**
		 * Fires after an FAQ interaction is tracked.
		 *
		 * @param array $payload Tracking data.
		 */
		do_action(
			'nlf_faq_tracked_event',
			array(
				'group_id'    => $group_id,
				'question_id' => $question_id,
				'state'       => $state,
			)
		);

		wp_send_json_success();
	}

	/**
	 * Enqueue group-specific CSS if custom styles are enabled.
	 *
	 * @param int $group_id Group ID.
	 */
	private static function maybe_enqueue_group_css( $group_id ) {
		if ( ! $group_id ) {
			return;
		}

		$group = Groups_Repository::get_group_by_id( $group_id );
		$use_custom_style = $group ? $group->use_custom_style : false;

		if ( empty( $use_custom_style ) ) {
			return;
		}

		$css_path = Style_Generator::get_group_css_file_path( $group_id );
		$css_url  = Style_Generator::get_group_css_file_url( $group_id );

		$uploads = wp_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? trailingslashit( $uploads['baseurl'] ) : '';

		if ( $css_url && $css_path && file_exists( $css_path ) && $baseurl && 0 === strpos( $css_url, $baseurl ) ) {
			wp_enqueue_style(
				'nlf-faq-group-' . $group_id,
				esc_url_raw( $css_url ),
				array( 'nlf-faq-generated' ),
				filemtime( $css_path )
			);
		}
	}

	/**
	 * Render FAQ shortcode.
	 *
	 * SECURITY:
	 * - All attributes sanitized via sanitize_shortcode_atts().
	 * - All output escaped via esc_html(), esc_attr().
	 * - HTML content sanitized via wp_kses_post().
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Enclosed content (unused for now).
	 *
	 * @return string
	 */
	public static function render_shortcode( $atts, $content = '' ) {
		$atts = self::sanitize_shortcode_atts(
			shortcode_atts(
				array(
					'title'      => __( 'Frequently Asked Questions', 'next-level-faq' ),
					'group'      => '',
					'group_slug' => '',
					'preset'     => '',
				),
			$atts,
			'krslys_nlf'
			)
		);

		$preset_slug      = Options::get_active_preset_slug( Options::get_options(), $atts['preset'] );
		$resolved_options = Options::resolve_for_preset( $preset_slug, Options::get_options() );

		$group_id = $atts['group'];

		if ( 0 === $group_id && '' !== $atts['group_slug'] ) {
			$group_obj = Groups_Repository::get_group_by_slug( $atts['group_slug'] );
			if ( $group_obj ) {
				$group_id = (int) $group_obj->id;
			}
		}

		// Enqueue group-specific CSS if custom styles enabled.
		if ( $group_id ) {
			self::maybe_enqueue_group_css( $group_id );
		}

		// Get group-specific settings from Groups_Repository.
		$settings = array();
		$group = $group_id ? Groups_Repository::get_group_by_id( $group_id ) : null;
		if ( $group && ! empty( $group->display_settings ) ) {
			$settings = $group->display_settings;
		}
		$defaults = array(
			'accordion_mode'  => false,
			'initial_state'   => 'all_closed',
			'animation_speed' => 'normal',
			'show_search'     => false,
			'show_counter'    => false,
			'smooth_scroll'   => true,
		);
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );

		$items = Repository::get_all_published_faqs( $group_id );

		$use_custom_style = $group ? $group->use_custom_style : false;

		// Determine the effective options for inline styles.
		// Priority: custom style CSS file > group theme > global preset.
		$effective_options = $resolved_options;
		if ( ! $use_custom_style && $group_id ) {
			$group_theme_options = Group_Admin::resolve_group_theme_options( $group_id );
			if ( is_array( $group_theme_options ) ) {
				$effective_options = $group_theme_options;
			}
		}
		$inline_style = $use_custom_style ? '' : Style_Generator::build_inline_style( $effective_options );

		// Determine icon style for the data attribute (needed for CSS icon rendering).
		$icon_style = isset( $effective_options['icon_style'] ) ? $effective_options['icon_style'] : 'plus_minus';

		if ( ! is_array( $items ) ) {
			$items = array();
		}

		$group_theme_slug = ( $group && isset( $group->theme_settings['theme'] ) ) ? $group->theme_settings['theme'] : '';

		$cache_context = array(
			'atts'             => array(
				'title' => $atts['title'],
			),
			'settings'         => $settings,
			'preset'           => $preset_slug,
			'use_custom_style' => $use_custom_style,
			'group_theme'      => $group_theme_slug,
			'icon_style'       => $icon_style,
		);

		if ( $group_id > 0 ) {
			$cached_output = Cache::get_rendered_group( $group_id, $cache_context );

			if ( $cached_output ) {
				return $cached_output;
			}
		}

		$faq_classes = array( 'nlf-faq' );
		if ( ! empty( $settings['accordion_mode'] ) ) {
			$faq_classes[] = 'nlf-faq--accordion';
		}
		if ( 'chevron' === $icon_style ) {
			$faq_classes[] = 'nlf-faq--icon-chevron';
		} elseif ( 'arrow' === $icon_style ) {
			$faq_classes[] = 'nlf-faq--icon-arrow';
		}
		$layout = isset( $effective_options['layout'] ) ? $effective_options['layout'] : 'flat';
		if ( 'flat' !== $layout ) {
			$faq_classes[] = 'nlf-faq--layout-' . sanitize_html_class( $layout );
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $faq_classes ) ); ?>"
			data-group-id="<?php echo esc_attr( $group_id ); ?>"
			data-animation-speed="<?php echo esc_attr( $settings['animation_speed'] ?? 'normal' ); ?>"
			data-accordion="<?php echo ! empty( $settings['accordion_mode'] ) ? '1' : '0'; ?>"
			data-smooth-scroll="<?php echo ! empty( $settings['smooth_scroll'] ) ? '1' : '0'; ?>"
			data-preset="<?php echo esc_attr( $preset_slug ); ?>"
			<?php if ( $inline_style ) : ?>
				style="<?php echo esc_attr( $inline_style ); ?>"
			<?php endif; ?>
			>
			<?php if ( '' !== $atts['title'] ) : ?>
				<h2 class="nlf-faq__title"><?php echo esc_html( $atts['title'] ); ?></h2>
			<?php endif; ?>

			<?php if ( ! empty( $settings['show_search'] ) ) : ?>
				<div class="nlf-faq-search">
					<input type="text" class="nlf-faq-search-input" placeholder="<?php esc_attr_e( 'Search FAQs...', 'next-level-faq' ); ?>" />
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $items ) ) : ?>
				<?php foreach ( $items as $index => $item ) : ?>
					<?php
					// Determine initial open state based on settings.
					$is_open = false;
					$initial_state = $settings['initial_state'] ?? 'all_closed';
					if ( 'first_open' === $initial_state && 0 === $index ) {
						$is_open = true;
					} elseif ( 'custom' === $initial_state && isset( $item->initial_state ) && 1 === (int) $item->initial_state ) {
						$is_open = true;
					}

					$is_active  = isset( $item->highlight ) ? ( 1 === (int) $item->highlight ) : false;
					$item_class = array();

					if ( $is_open ) {
						$item_class[] = 'is-open';
					}
					if ( $is_active ) {
						$item_class[] = 'nlf-faq__item--highlight';
					}
					?>
					<div class="nlf-faq__item <?php echo esc_attr( implode( ' ', $item_class ) ); ?>" data-faq-id="<?php echo esc_attr( $item->id ); ?>">
						<div class="nlf-faq__question">
							<?php if ( ! empty( $settings['show_counter'] ) ) : ?>
								<span class="nlf-faq__counter"><?php echo esc_html( $index + 1 ); ?>.</span>
							<?php endif; ?>
							<span><?php echo esc_html( (string) $item->question ); ?></span>
							<span class="nlf-faq__icon" aria-hidden="true"></span>
						</div>
						<div class="nlf-faq__answer">
							<?php echo wp_kses_post( wpautop( (string) $item->answer ) ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<p class="nlf-faq__empty">
					<?php esc_html_e( 'No FAQs found yet. Add some FAQs in the admin to populate this section.', 'next-level-faq' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php

		$output = trim( ob_get_clean() );

		if ( $group_id > 0 ) {
			Cache::set_rendered_group( $group_id, $cache_context, $output );
		}

		return $output;
	}

	/**
	 * Sanitize shortcode attributes.
	 *
	 * SECURITY:
	 * - title: sanitize_text_field() to prevent XSS.
	 * - group: absint() to ensure positive integer.
	 * - group_slug: sanitize_title() for safe slug format.
	 *
	 * @param array $atts Raw shortcode attributes.
	 *
	 * @return array
	 */
	private static function sanitize_shortcode_atts( array $atts ) : array {
		return array(
			'title'      => sanitize_text_field( $atts['title'] ?? '' ),
			'group'      => absint( $atts['group'] ?? 0 ),
			'group_slug' => sanitize_title( $atts['group_slug'] ?? '' ),
			'preset'     => sanitize_key( $atts['preset'] ?? '' ),
		);
	}
}
