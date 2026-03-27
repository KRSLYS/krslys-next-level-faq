<?php
/**
 * Shared UI components for admin screens.
 *
 * @package Krslys\NextLevelFaq
 */

namespace Krslys\NextLevelFaq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI components helper.
 */
class Admin_UI_Components {

	/**
	 * Render reusable preview container.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $context Context identifier.
	 */
	public static function preview_container( $post_id, $context = 'main' ) {
		?>
		<div class="nlf-preview-container" data-group-id="<?php echo esc_attr( $post_id ); ?>" data-preview="<?php echo esc_attr( $context ); ?>">
			<div class="nlf-preview-loading">
				<div class="nlf-skeleton-faq">
					<div class="nlf-skeleton-line nlf-skeleton-title"></div>
					<div class="nlf-skeleton-item">
						<div class="nlf-skeleton-line nlf-skeleton-question"></div>
						<div class="nlf-skeleton-line nlf-skeleton-answer"></div>
					</div>
					<div class="nlf-skeleton-item">
						<div class="nlf-skeleton-line nlf-skeleton-question"></div>
						<div class="nlf-skeleton-line nlf-skeleton-answer"></div>
					</div>
					<div class="nlf-skeleton-item">
						<div class="nlf-skeleton-line nlf-skeleton-question"></div>
						<div class="nlf-skeleton-line nlf-skeleton-answer"></div>
					</div>
				</div>
			</div>
			<div class="nlf-preview-content"></div>
		</div>
		<?php
	}

	/**
	 * Render onboarding card for empty FAQ groups.
	 */
	public static function onboarding_card() {
		?>
		<div class="nlf-onboarding-card" aria-live="polite">
			<div class="nlf-onboarding-icon">
				<span class="dashicons dashicons-lightbulb"></span>
			</div>
			<div class="nlf-onboarding-content">
				<h3><?php esc_html_e( 'Let’s build your FAQ group', 'next-level-faq' ); ?></h3>
				<p><?php esc_html_e( 'Follow these quick steps to publish your first FAQ section.', 'next-level-faq' ); ?></p>
				<ol>
					<li><strong><?php esc_html_e( 'Add questions', 'next-level-faq' ); ?></strong> — <?php esc_html_e( 'Capture what customers ask most often.', 'next-level-faq' ); ?></li>
					<li><strong><?php esc_html_e( 'Customize the look', 'next-level-faq' ); ?></strong> — <?php esc_html_e( 'Match your brand colors and typography.', 'next-level-faq' ); ?></li>
					<li><strong><?php esc_html_e( 'Preview & publish', 'next-level-faq' ); ?></strong> — <?php esc_html_e( 'Review the live preview before publishing.', 'next-level-faq' ); ?></li>
				</ol>
				<div class="nlf-onboarding-actions">
					<button type="button" class="button button-primary button-hero nlf-onboarding-start">
						<?php esc_html_e( 'Start adding questions', 'next-level-faq' ); ?>
					</button>
					<button type="button" class="button button-secondary" data-switch-tab="appearance">
						<?php esc_html_e( 'Explore appearance options', 'next-level-faq' ); ?> →
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render generic empty state panel.
	 *
	 * @param array $args Arguments.
	 */
	public static function empty_state( $args ) {
		$defaults = array(
			'icon'        => 'dashicons-editor-help',
			'title'       => '',
			'description' => '',
			'primary'     => null,
			'secondary'   => null,
		);

		$args = wp_parse_args( $args, $defaults );
		?>
		<div class="nlf-empty-state">
			<?php if ( $args['icon'] ) : ?>
				<div class="nlf-empty-icon">
					<span class="dashicons <?php echo esc_attr( $args['icon'] ); ?>"></span>
				</div>
			<?php endif; ?>

			<?php if ( $args['title'] ) : ?>
				<h3><?php echo esc_html( $args['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( $args['description'] ) : ?>
				<p><?php echo esc_html( $args['description'] ); ?></p>
			<?php endif; ?>

			<div class="nlf-empty-actions">
				<?php if ( $args['primary'] ) : ?>
					<?php
					$button_class = 'button button-primary button-hero';
					if ( ! empty( $args['primary']['data']['add-row'] ) ) {
						$button_class .= ' nlf-faq-group-add-row-btn';
					}
					?>
					<button type="button"
						class="<?php echo esc_attr( $button_class ); ?>"
						<?php echo ! empty( $args['primary']['id'] ) ? 'id="' . esc_attr( $args['primary']['id'] ) . '"' : ''; ?>
						<?php echo ! empty( $args['primary']['data'] ) ? self::build_data_attributes( $args['primary']['data'] ) : ''; ?>>
						<?php echo esc_html( $args['primary']['label'] ); ?>
					</button>
				<?php endif; ?>

				<?php if ( $args['secondary'] ) : ?>
					<button type="button"
						class="button button-secondary"
						<?php echo ! empty( $args['secondary']['data'] ) ? self::build_data_attributes( $args['secondary']['data'] ) : ''; ?>>
						<?php echo esc_html( $args['secondary']['label'] ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render mobile preview notice.
	 * Shown on small screens where preview cannot display properly.
	 */
	public static function mobile_preview_notice() {
		?>
		<div class="nlf-preview-mobile-notice">
			<div class="nlf-preview-mobile-notice__inner">
				<div class="nlf-preview-mobile-notice__icon">
					<span class="dashicons dashicons-laptop"></span>
				</div>
				<div class="nlf-preview-mobile-notice__content">
					<h4 class="nlf-preview-mobile-notice__title"><?php esc_html_e( 'Preview requires a larger screen', 'next-level-faq' ); ?></h4>
					<p class="nlf-preview-mobile-notice__desc"><?php esc_html_e( 'The live preview needs more space to display accurately. Your changes are saved automatically.', 'next-level-faq' ); ?></p>
					<span class="nlf-preview-mobile-notice__hint">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Open on desktop or tablet to preview', 'next-level-faq' ); ?>
					</span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Helper to build data attributes.
	 *
	 * @param array $data Key => value pairs.
	 *
	 * @return string
	 */
	private static function build_data_attributes( $data ) {
		$attributes = '';

		foreach ( $data as $key => $value ) {
			$attributes .= sprintf( ' data-%1$s="%2$s"', esc_attr( $key ), esc_attr( $value ) );
		}

		return $attributes;
	}
}

