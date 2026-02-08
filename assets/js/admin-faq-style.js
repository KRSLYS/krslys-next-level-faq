(function ($) {
	'use strict';

	var presetRegistry = (window.nlfFaqAdmin && nlfFaqAdmin.presets) || {};
	var activePreset = (window.nlfFaqAdmin && nlfFaqAdmin.activePreset) || null;
	var optionKey = (window.nlfFaqAdmin && nlfFaqAdmin.optionKey) || 'nlf_faq_style_options';

	function applyPreviewFromData() {
		var root = $('#nlf-faq-preview-root');
		if (!root.length) {
			return;
		}

		var container = root.find('.nlf-faq');
		var icons = container.find('.nlf-faq__icon');
		var iconStyle = root.data('icon-style') || 'plus_minus';

		// Container styles
		container.css({
			backgroundColor: root.data('container-background'),
			borderColor: root.data('container-border-color'),
			borderRadius: root.data('container-border-radius') + 'px',
			padding: root.data('container-padding') + 'px',
			boxShadow: root.data('shadow') === 1 || root.data('shadow') === '1'
				? '0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24)'
				: 'none'
		});

		// Item spacing
		container.find('.nlf-faq__item + .nlf-faq__item').css('margin-top', root.data('gap-between-items') + 'px');

		// Question styles
		container.find('.nlf-faq__question').css({
			color: root.data('question-color'),
			fontSize: root.data('question-font-size') + 'px',
			fontWeight: root.data('question-font-weight')
		});

		// Answer styles
		container.find('.nlf-faq__answer').css({
			color: root.data('answer-color'),
			fontSize: root.data('answer-font-size') + 'px'
		});

		// Icon color
		icons.css('color', root.data('accent-color'));

		// Icon style - inject dynamic CSS
		var styleId = 'nlf-faq-preview-icon-style';
		var $existingStyle = $('#' + styleId);
		if ($existingStyle.length) {
			$existingStyle.remove();
		}

		var iconCss = '';
		if (iconStyle === 'chevron') {
			iconCss = '#nlf-faq-preview-root .nlf-faq__icon::before {' +
				'content: "›";' +
				'display: block;' +
				'transform: rotate(90deg);' +
				'transition: transform 200ms ease;' +
				'}' +
				'#nlf-faq-preview-root .nlf-faq__item.is-open .nlf-faq__icon::before {' +
				'transform: rotate(270deg);' +
				'}';
		} else {
			iconCss = '#nlf-faq-preview-root .nlf-faq__icon::before {' +
				'content: "+";' +
				'font-weight: 700;' +
				'font-size: 1.125rem;' +
				'line-height: 1;' +
				'}' +
				'#nlf-faq-preview-root .nlf-faq__item.is-open .nlf-faq__icon::before {' +
				'content: "-";' +
				'}';
		}

		if (iconCss) {
			$('<style id="' + styleId + '">' + iconCss + '</style>').appendTo('head');
		}
	}

	function updateDataProp(prop, value) {
		var root = $('#nlf-faq-preview-root');
		if (!root.length) {
			return;
		}

		// Map property names to data attributes
		var propMap = {
			'container_background': 'container-background',
			'container_border_color': 'container-border-color',
			'container_border_radius': 'container-border-radius',
			'container_padding': 'container-padding',
			'question_color': 'question-color',
			'question_font_size': 'question-font-size',
			'question_font_weight': 'question-font-weight',
			'answer_color': 'answer-color',
			'answer_font_size': 'answer-font-size',
			'accent_color': 'accent-color',
			'gap_between_items': 'gap-between-items',
			'shadow': 'shadow',
			'animation': 'animation',
			'icon_style': 'icon-style',
			'preset': 'preset'
		};

		var dataKey = propMap[prop];
		if (!dataKey) {
			return;
		}

		// Parse numeric values
		var numericProps = ['container_border_radius', 'container_padding', 'question_font_size', 
			'question_font_weight', 'answer_font_size', 'gap_between_items'];
		if (numericProps.indexOf(prop) !== -1) {
			value = parseInt(value, 10) || (prop === 'question_font_weight' ? 600 : 
				prop === 'question_font_size' ? 16 : prop === 'answer_font_size' ? 14 : 
				prop === 'gap_between_items' ? 12 : 0);
		} else if (prop === 'shadow') {
			value = value ? 1 : 0;
		}

		root.data(dataKey, value);
		applyPreviewFromData();
	}

	function getPresetValues(slug) {
		if (!slug || !presetRegistry[slug]) {
			return null;
		}
		return presetRegistry[slug].values || null;
	}

	function setActivePresetCard(slug) {
		activePreset = slug;
		$('.nlf-preset-card').removeClass('active');
		$('.nlf-preset-card input[data-preset-choice]').each(function () {
			if ($(this).val() === slug) {
				$(this).closest('.nlf-preset-card').addClass('active');
			}
		});
	}

	function applyPreset(slug) {
		var values = getPresetValues(slug);
		if (!values) {
			return;
		}

		var optionPrefix = optionKey + '[';

		Object.keys(values).forEach(function (key) {
			var selector = '[name="' + optionPrefix + key + '"]';
			var $field = $(selector);
			var val = values[key];

			if (!$field.length) {
				return;
			}

			if ($field.is(':checkbox')) {
				$field.prop('checked', !!val);
			} else {
				$field.val(val);
			}

			if ($field.hasClass('nlf-color-field') && typeof $field.wpColorPicker === 'function' && $field.data('wpWpColorPicker')) {
				$field.wpColorPicker('color', val);
			}

			updateDataProp(key, val);
		});

		// Set preset radios.
		$('input[data-preset-choice]').prop('checked', false);
		$('input[data-preset-choice][value="' + slug + '"]').prop('checked', true);

		// IMPORTANT: Update the hidden preset field inside the form
		// The radio buttons are outside the form, so we need to sync this hidden field
		$('#nlf-faq-hidden-preset').val(slug);

		updateDataProp('preset', slug);
		setActivePresetCard(slug);
	}

	$(function () {
		// Initialize WordPress color picker for all color fields.
		if (typeof $.fn.wpColorPicker !== 'undefined') {
			$('.nlf-color-field').wpColorPicker({
				change: function (event, ui) {
					var $el = $(this);
					var prop = $el.data('preview-prop');
					var val = ui.color.toString();
					updateDataProp(prop, val);
				},
				clear: function () {
					var $el = $(this);
					var prop = $el.data('preview-prop');
					updateDataProp(prop, '');
				}
			});
		}

		applyPreviewFromData();

		$('#nlf-faq-style-form').on('input change', '[data-preview-prop]', function () {
			var $el = $(this);
			
			// Skip color fields as they're handled by color picker callbacks.
			if ($el.hasClass('nlf-color-field')) {
				return;
			}

			var prop = $el.data('preview-prop');
			var val;

			if ($el.is(':checkbox')) {
				val = $el.is(':checked');
			} else {
				val = $el.val();
			}

			updateDataProp(prop, val);
		});

		// Preset selection.
		$(document).on('change', 'input[data-preset-choice]', function () {
			var slug = $(this).val();
			applyPreset(slug);
		});

		// Initialize active preset highlighting.
		if (activePreset) {
			setActivePresetCard(activePreset);
		}

		// AJAX form submission for instant save.
		var $form = $('#nlf-faq-style-form');
		var $submit = $form.find('input[type="submit"], button[type="submit"]');
		var originalText = $submit.val() || $submit.text();

		$form.on('submit', function (e) {
			e.preventDefault();

			if (!$submit.length || typeof nlfFaqAdmin === 'undefined') {
				return;
			}

			// Update button state
			$submit.prop('disabled', true);
			if ($submit.is('input')) {
				$submit.val(nlfFaqAdmin.i18n.saving);
			} else {
				$submit.text(nlfFaqAdmin.i18n.saving);
			}

			// Remove any previous notices
			$('.nlf-ajax-notice').remove();

			// Serialize form data
			var formData = $form.serialize();
			formData += '&action=nlf_save_settings_ajax';
			formData += '&nonce=' + (nlfFaqAdmin.saveNonce || '');

			// Send AJAX request
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				success: function (response) {
					if (response.success) {
						// Show success message
						if ($submit.is('input')) {
							$submit.val(nlfFaqAdmin.i18n.saved);
						} else {
							$submit.text(nlfFaqAdmin.i18n.saved);
						}

					// Show success notice (escape server message to prevent XSS).
					var safeSuccessMsg = $('<span>').text(response.data.message || 'Settings saved successfully!').html();
					$('<div class="notice notice-success is-dismissible nlf-ajax-notice" role="status" aria-live="polite"><p>' + 
						safeSuccessMsg + 
						'</p></div>')
						.insertAfter('.wrap h1')
						.hide()
						.fadeIn();

						// Restore button after delay
						setTimeout(function () {
							$submit.prop('disabled', false);
							if ($submit.is('input')) {
								$submit.val(originalText);
							} else {
								$submit.text(originalText);
							}
						}, 1500);

						// Auto-hide notice after 3 seconds
						setTimeout(function () {
							$('.nlf-ajax-notice').fadeOut(function () {
								$(this).remove();
							});
						}, 3000);
					} else {
						// Show error message
					var errorMsg = response.data && response.data.message 
						? response.data.message 
						: 'Failed to save settings. Please try again.';

					// Escape server message to prevent XSS.
					var safeErrorMsg = $('<span>').text(errorMsg).html();
					$('<div class="notice notice-error is-dismissible nlf-ajax-notice" role="alert" aria-live="assertive"><p>' + 
						safeErrorMsg + 
						'</p></div>')
						.insertAfter('.wrap h1')
						.hide()
						.fadeIn();

						// Restore button
						$submit.prop('disabled', false);
						if ($submit.is('input')) {
							$submit.val(originalText);
						} else {
							$submit.text(originalText);
						}
					}
				},
				error: function (xhr, status, error) {
					// Show error message.
					$('<div class="notice notice-error is-dismissible nlf-ajax-notice" role="alert" aria-live="assertive"><p>' + 
						'Network error: Failed to save settings. Please check your connection and try again.' + 
						'</p></div>')
						.insertAfter('.wrap h1')
						.hide()
						.fadeIn();

					// Restore button
					$submit.prop('disabled', false);
					if ($submit.is('input')) {
						$submit.val(originalText);
					} else {
						$submit.text(originalText);
					}
				}
			});
		});
	});
})(jQuery);


