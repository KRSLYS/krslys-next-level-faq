<?php
/**
 * Front-end rendering and assets.
 *
 * @package Krslys\NextLevelFaqAccordion
 */

namespace Krslys\NextLevelFaqAccordion;

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
	 * Collected FAQ schema entities to output in wp_head.
	 *
	 * @var array[]
	 */
	private static $schema_queue = array();

	/**
	 * Bootstrap all frontend hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
		add_action( 'init', array( __CLASS__, 'register_tracking_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'wp', array( __CLASS__, 'prepare_faq_schema' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_faq_schema' ), 99 );
	}

	/**
	 * Register shortcodes.
	 */
	public static function register_shortcodes() {
		add_shortcode( 'krslys_nlfa', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Register front-end styles and scripts.
	 *
	 * Assets are registered here but only enqueued when a FAQ
	 * shortcode or block is actually rendered on the page.
	 *
	 * SECURITY: Uses esc_url_raw() for CSS URL.
	 */
	public static function enqueue_styles() {
		// Register global CSS (enqueued on demand in render_shortcode).
		$css_path = Style_Generator::get_css_file_path();
		$css_url  = Style_Generator::get_css_file_url();

		$uploads = wp_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? trailingslashit( $uploads['baseurl'] ) : '';

		if ( $css_url && $css_path && file_exists( $css_path ) && $baseurl && 0 === strpos( $css_url, $baseurl ) ) {
			wp_register_style(
				'nlf-faq-generated',
				esc_url_raw( $css_url ),
				array(),
				filemtime( $css_path )
			);
		}

		// Register JS (enqueued on demand in render_shortcode).
		wp_register_script(
			'nlf-faq-frontend',
			krslys_nlfa_asset_url( 'assets/js/frontend-faq.js' ),
			array(),
			KRSLYS_NLFA_VERSION,
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
	 * Enqueue registered assets when a FAQ is actually rendered.
	 *
	 * Called from render_shortcode() so assets only load on pages
	 * that contain at least one FAQ group.
	 */
	private static function enqueue_frontend_assets() {
		if ( ! wp_style_is( 'nlf-faq-generated', 'enqueued' ) ) {
			wp_enqueue_style( 'nlf-faq-generated' );
		}
		if ( ! wp_script_is( 'nlf-faq-frontend', 'enqueued' ) ) {
			wp_enqueue_script( 'nlf-faq-frontend' );
		}
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
		$state       = isset( $_POST['state'] ) ? sanitize_key( wp_unslash( $_POST['state'] ) ) : '';

		if ( $group_id <= 0 || $question_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tracking payload.', 'krslys-next-level-faq-accordion' ) ), 400 );
		}

		// Verify the group exists in the custom table.
		$group = \Krslys\NextLevelFaqAccordion\Groups_Repository::get_group_by_id( $group_id );
		if ( ! $group ) {
			wp_send_json_error( array( 'message' => __( 'Group not found.', 'krslys-next-level-faq-accordion' ) ), 404 );
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
			'krslys_nlfa_faq_tracked_event',
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
		// Enqueue CSS/JS only when a FAQ is actually rendered.
		self::enqueue_frontend_assets();

		$atts = self::sanitize_shortcode_atts(
			shortcode_atts(
				array(
					'title'      => '',
					'group'      => '',
					'group_slug' => '',
					'preset'     => '',
				),
			$atts,
			'krslys_nlfa'
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

		// Use group title when shortcode/block title is empty.
		if ( '' === $atts['title'] && $group && ! empty( $group->title ) ) {
			$atts['title'] = $group->title;
		}

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
			dir="auto"
			data-group-id="<?php echo esc_attr( $group_id ); ?>"
			data-animation-speed="<?php echo esc_attr( $settings['animation_speed'] ?? 'normal' ); ?>"
			data-accordion="<?php echo esc_attr( ! empty( $settings['accordion_mode'] ) ? '1' : '0' ); ?>"
			data-smooth-scroll="<?php echo esc_attr( ! empty( $settings['smooth_scroll'] ) ? '1' : '0' ); ?>"
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
					<input type="text" class="nlf-faq-search-input" placeholder="<?php esc_attr_e( 'Search FAQs...', 'krslys-next-level-faq-accordion' ); ?>" aria-label="<?php esc_attr_e( 'Search FAQs', 'krslys-next-level-faq-accordion' ); ?>" />
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

					// Unique IDs for ARIA linkage.
					$question_id = 'nlf-q-' . $group_id . '-' . $item->id;
					$answer_id   = 'nlf-a-' . $group_id . '-' . $item->id;
					?>
					<div class="nlf-faq__item <?php echo esc_attr( implode( ' ', $item_class ) ); ?>" data-faq-id="<?php echo esc_attr( $item->id ); ?>">
						<button type="button"
							class="nlf-faq__question"
							id="<?php echo esc_attr( $question_id ); ?>"
							aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
							aria-controls="<?php echo esc_attr( $answer_id ); ?>">
							<?php if ( ! empty( $settings['show_counter'] ) ) : ?>
								<span class="nlf-faq__counter"><?php echo esc_html( $index + 1 ); ?>.</span>
							<?php endif; ?>
							<span><?php echo esc_html( (string) $item->question ); ?></span>
							<span class="nlf-faq__icon" aria-hidden="true"></span>
						</button>
						<div class="nlf-faq__answer"
							id="<?php echo esc_attr( $answer_id ); ?>"
							role="region"
							aria-labelledby="<?php echo esc_attr( $question_id ); ?>"
							<?php if ( ! $is_open ) : ?>
								aria-hidden="true"
							<?php endif; ?>
							>
							<?php echo wp_kses_post( wpautop( (string) $item->answer ) ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<p class="nlf-faq__empty">
					<?php esc_html_e( 'No FAQs found yet. Add some FAQs in the admin to populate this section.', 'krslys-next-level-faq-accordion' ); ?>
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
	 * Pre-scan the current page content for FAQ shortcodes/blocks
	 * and build schema data before wp_head fires.
	 *
	 * Hooked to 'wp' action which runs after the query but before
	 * template rendering, allowing schema output in wp_head.
	 */
	public static function prepare_faq_schema() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		$enable_schema = Settings_Repository::get_setting( 'enable_schema_markup', true );
		if ( ! $enable_schema ) {
			return;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}

		$content = $post->post_content;
		$group_ids = array();

		// Detect shortcodes: [krslys_nlfa group="ID"]
		if ( has_shortcode( $content, 'krslys_nlfa' ) ) {
			preg_match_all( '/\[krslys_nlfa\s[^\]]*group=["\']?(\d+)["\']?/', $content, $matches );
			if ( ! empty( $matches[1] ) ) {
				$group_ids = array_merge( $group_ids, array_map( 'intval', $matches[1] ) );
			}
		}

		// Detect Gutenberg blocks: krslys-next-level/faq
		if ( has_block( 'krslys-next-level/faq', $content ) ) {
			$blocks = parse_blocks( $content );
			foreach ( $blocks as $block ) {
				if ( 'krslys-next-level/faq' === $block['blockName'] && ! empty( $block['attrs']['groupId'] ) ) {
					$group_ids[] = (int) $block['attrs']['groupId'];
				}
			}
		}

		$group_ids = array_unique( array_filter( $group_ids ) );
		if ( empty( $group_ids ) ) {
			return;
		}

		foreach ( $group_ids as $gid ) {
			$group = Groups_Repository::get_group_by_id( $gid );
			if ( ! $group || ! in_array( $group->status, array( 'active', 'publish' ), true ) ) {
				continue;
			}

			// Skip accordion groups — FAQPage schema is only for FAQ content.
			$group_type = isset( $group->type ) ? $group->type : 'faq';
			if ( 'accordion' === $group_type ) {
				continue;
			}

			// Check per-group disable.
			$display = is_array( $group->display_settings ) ? $group->display_settings : array();
			if ( ! empty( $display['disable_schema'] ) ) {
				continue;
			}

			$items = Repository::get_all_published_faqs( $gid );
			if ( empty( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				self::$schema_queue[] = array(
					'@type'          => 'Question',
					'name'           => wp_strip_all_tags( (string) $item->question ),
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => wp_kses_post( wpautop( (string) $item->answer ) ),
					),
				);
			}
		}
	}

	/**
	 * Print consolidated FAQPage JSON-LD in wp_head.
	 *
	 * Outputs a single FAQPage schema containing all FAQ groups
	 * found on the current page.
	 *
	 * @see https://schema.org/FAQPage
	 * @see https://developers.google.com/search/docs/appearance/structured-data/faqpage
	 */
	public static function print_faq_schema() {
		if ( empty( self::$schema_queue ) ) {
			return;
		}

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => self::$schema_queue,
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD encoded via wp_json_encode().
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
