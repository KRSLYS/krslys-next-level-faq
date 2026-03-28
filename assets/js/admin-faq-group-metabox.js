(() => {
	'use strict';

	const doc = document;

	const $$ = (selector, context = doc) => Array.from(context.querySelectorAll(selector));
	const $ = (selector, context = doc) => context.querySelector(selector);

	const hasTabs = () => Boolean($('.nlf-faq-tabs-nav'));

	const previewContexts = {
		main: () => $$('.nlf-preview-container[data-preview="main"]'),
		appearance: () => $$('.nlf-preview-container[data-preview="appearance"]'),
	};

	const previewState = {
		auto: {
			main: true,
			appearance: true,
		},
		timers: new Map(),
		delay: 400,
	};

	function init() {
		if (!hasTabs()) {
			return;
		}

		initTabs();
		initThemeSelection();
		initCustomStyleToggle();
		initColorPickers();
		initResetButtons();
		initUnsavedWarning();
		initDeviceToggle();
		initPreviewRefresh();
		initAutoRefreshToggle();
		initHelpTooltips();
		initEmptyStateHandlers();
		initQuestionList();
		initAjaxSave();
	}

	// Tabs
	function initTabs() {
		const tabs = $$('.nlf-faq-tab-button');
		const panels = $$('.nlf-faq-tab-panel');

		const switchToTab = (tabEl) => {
			if (!tabEl) {
				return;
			}

			const target = tabEl.getAttribute('data-tab');

			tabs.forEach((tab) => {
				tab.classList.toggle('active', tab === tabEl);
				tab.setAttribute('aria-selected', tab === tabEl ? 'true' : 'false');
			});

			panels.forEach((panel) => {
				const isTarget = panel.getAttribute('data-tab') === target;
				panel.classList.toggle('active', isTarget);
				if (isTarget) {
					panel.removeAttribute('hidden');
					panel.focus();
				} else {
					panel.setAttribute('hidden', 'true');
				}
			});

			if (target === 'preview') {
				requestPreview('main', true);
			}

			if (target === 'appearance') {
				requestPreview('appearance', true);
			}
		};

		tabs.forEach((tab) => {
			tab.addEventListener('click', (event) => {
				event.preventDefault();
				switchToTab(tab);
			});

			tab.addEventListener('keydown', (event) => {
				const currentIndex = tabs.indexOf(tab);
				let nextIndex = null;

				switch (event.key) {
					case 'ArrowRight':
					case 'ArrowDown':
						nextIndex = (currentIndex + 1) % tabs.length;
						break;
					case 'ArrowLeft':
					case 'ArrowUp':
						nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
						break;
					case 'Home':
						nextIndex = 0;
						break;
					case 'End':
						nextIndex = tabs.length - 1;
						break;
					default:
						return;
				}

				event.preventDefault();
				switchToTab(tabs[nextIndex]);
				tabs[nextIndex].focus();
			});
		});

		doc.addEventListener('click', (event) => {
			const trigger = event.target.closest('[data-switch-tab]');
			if (!trigger) {
				return;
			}
			event.preventDefault();
			const targetTab = trigger.getAttribute('data-switch-tab');
			const tabButton = tabs.find((tab) => tab.getAttribute('data-tab') === targetTab);
			if (tabButton) {
				switchToTab(tabButton);
			}
		});
	}

	// Theme selector
	function initThemeSelection() {
		const options = $$('.nlf-theme-option');
		if (!options.length) {
			return;
		}

		options.forEach((option) => {
			const input = $('input[type="radio"]', option);
			if (!input) {
				return;
			}

			input.addEventListener('change', () => {
				options.forEach((opt) => opt.classList.remove('is-active'));
				option.classList.add('is-active');
				requestPreview('appearance');
			});
		});
	}

	function initCustomStyleToggle() {
		const toggle = $('#nlf-use-custom-style-toggle');
		const fields = $('.nlf-custom-style-fields');

		if (!toggle || !fields) {
			return;
		}

		toggle.addEventListener('change', () => {
			fields.style.display = toggle.checked ? '' : 'none';
			requestPreview('appearance');
		});
	}

	// Color pickers (WordPress dependency)
	function initColorPickers() {
		if (!window.jQuery || typeof window.jQuery.fn.wpColorPicker === 'undefined') {
			return;
		}

		window.jQuery('.nlf-color-picker').wpColorPicker({
			change: () => requestPreview('appearance'),
			clear: () => requestPreview('appearance'),
		});
	}

	function initResetButtons() {
		doc.addEventListener('click', (event) => {
			const button = event.target.closest('[data-reset]');
			if (!button) {
				return;
			}

			event.preventDefault();

			if (button.getAttribute('data-reset') === 'theme') {
				resetThemeSelection();
			}

			if (button.getAttribute('data-reset') === 'styles') {
				resetCustomStyles();
			}
		});
	}

	function resetThemeSelection() {
		const selector = $('.nlf-theme-selector');
		if (!selector) {
			return;
		}

		const defaultTheme = selector.getAttribute('data-default-theme') || 'default';
		const defaultRadio = doc.getElementById(`theme_${defaultTheme}`);

		if (defaultRadio) {
			defaultRadio.checked = true;
			defaultRadio.dispatchEvent(new Event('change', { bubbles: true }));
		}

		$$('.nlf-theme-option').forEach((option) => {
			option.classList.toggle('is-active', option.contains(defaultRadio));
		});

		$$('.nlf-theme-color').forEach((input) => {
			input.value = '';
			input.dispatchEvent(new Event('change', { bubbles: true }));
			if (window.jQuery && window.jQuery(input).data('wpColorPicker')) {
				window.jQuery(input).wpColorPicker('color', '');
			}
		});

		requestPreview('appearance', true);
	}

	function resetCustomStyles() {
		const fields = $('.nlf-custom-style-fields');
		if (!fields) {
			return;
		}

		let defaults = fields.getAttribute('data-default-styles');
		if (!defaults) {
			return;
		}

		try {
			defaults = JSON.parse(defaults);
		} catch (error) {
			return;
		}

		Object.keys(defaults).forEach((key) => {
			const input = fields.querySelector(`[name="nlf_faq_group_custom_styles[${key}]"]`);
			if (!input) {
				return;
			}

			input.value = defaults[key];
			if (window.jQuery && window.jQuery(input).data('wpColorPicker')) {
				window.jQuery(input).wpColorPicker('color', defaults[key]);
			}
		});

		requestPreview('appearance', true);
	}

	function requestPreview(context, immediate = false) {
		if (!previewContexts[context]) {
			return;
		}

		if (!previewState.auto[context] && !immediate) {
			return;
		}

		if (previewState.timers.has(context)) {
			clearTimeout(previewState.timers.get(context));
		}

		const delay = immediate ? 0 : previewState.delay;
		const timer = setTimeout(() => {
			loadLivePreview(previewContexts[context]());
			previewState.timers.delete(context);
		}, delay);

		previewState.timers.set(context, timer);
	}

	// Live preview via fetch
	function loadLivePreview(targetNodes) {
		const containers = targetNodes && targetNodes.length ? targetNodes : $$('.nlf-preview-container');

		containers.forEach((container) => {
			if (!container) {
				return;
			}

			const groupId = parseInt(container.getAttribute('data-group-id'), 10);
			if (!groupId) {
				return;
			}

			const loading = $('.nlf-preview-loading', container);
			const content = $('.nlf-preview-content', container);
			if (!loading || !content) {
				return;
			}

			loading.style.display = '';
			content.classList.remove('loaded');
			content.style.display = 'none';

			const params = new URLSearchParams();
			params.append('action', 'nlf_get_group_preview');
			params.append('group_id', String(groupId));
			params.append('nonce', nlfGroupData.nonce);

			// Send the currently selected theme so the preview reflects unsaved changes.
			const selectedTheme = $('input[name="nlf_faq_group_theme"]:checked');
			if (selectedTheme) {
				params.append('theme', selectedTheme.value);
			}

			// Send custom color overrides.
			$$('.nlf-theme-color').forEach((input) => {
				const key = input.getAttribute('data-color-key');
				const val = input.value;
				if (key && val) {
					params.append('theme_custom_' + key, val);
				}
			});

			fetch(nlfGroupData.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: params.toString(),
			})
				.then((response) => response.json())
				.then((result) => {
					if (result.success && result.data && result.data.html) {
						content.innerHTML = result.data.html;
						loading.style.display = 'none';
						content.style.display = '';
						content.classList.add('loaded');

						if (typeof window.nlfInitFaq === 'function') {
							window.nlfInitFaq(content);
						}
				} else {
					const message = result.data && result.data.message ? result.data.message : 'Failed to load preview.';
					const errorDiv = document.createElement('div');
					errorDiv.className = 'nlf-preview-error';
					errorDiv.innerHTML = '<span class="dashicons dashicons-warning"></span>';
					const p = document.createElement('p');
					p.textContent = message;
					errorDiv.appendChild(p);
					loading.textContent = '';
					loading.appendChild(errorDiv);
				}
				})
				.catch(() => {
					loading.innerHTML = '<div class="nlf-preview-error"><span class="dashicons dashicons-warning"></span><p>Error loading preview. Please save the group and try again.</p></div>';
				});
		});
	}

	function renumberGroupCheckboxes() {
		const rows = $$('#nlf-faq-group-questions-body .nlf-faq-question-row');
		rows.forEach((row, index) => {
			setCheckboxName(row, 'nlf_faq_group_visible', index);
			setCheckboxName(row, 'nlf_faq_group_open', index);
			setCheckboxName(row, 'nlf_faq_group_highlight', index);
		});
	}

	function setCheckboxName(row, name, index) {
		const input = row.querySelector(`input[name^="${name}"]`);
		if (!input) {
			return;
		}
		input.setAttribute('name', `${name}[${index}]`);
	}

	function initNewEditor(row) {
		const textarea = row.querySelector('.nlf-faq-group-answer-editor');
		if (!textarea || !window.wp || !window.wp.editor) {
			return;
		}

		if (!textarea.id) {
			textarea.id = `nlf-faq-group-answer-${Date.now()}`;
		}

		const editorAPI =
			(window.wp.oldEditor && typeof window.wp.oldEditor.initialize === 'function')
				? window.wp.oldEditor
				: (window.wp.editor && typeof window.wp.editor.initialize === 'function' ? window.wp.editor : null);

		if (editorAPI) {
			editorAPI.initialize(textarea.id, {
				tinymce: { wpautop: true },
				quicktags: true,
				mediaButtons: false,
			});
		}
	}

	function initUnsavedWarning() {
		const indicator = doc.createElement('div');
		indicator.className = 'nlf-unsaved-indicator';
		indicator.innerHTML = '<span class="dashicons dashicons-warning"></span><span>Unsaved changes</span>';
		doc.body.appendChild(indicator);

		let hasChanges = false;

		const form = $('#post');
		if (!form) {
			return;
		}

		const revealIndicator = () => {
			if (!hasChanges) {
				hasChanges = true;
				indicator.classList.add('visible');
			}
		};

		form.addEventListener('input', revealIndicator, true);
		form.addEventListener('change', revealIndicator, true);

		const resetIndicator = () => {
			hasChanges = false;
			indicator.classList.remove('visible');
		};

		const publish = $('#publish');
		const saveDraft = $('#save-post');

		[publish, saveDraft].forEach((button) => {
			if (!button) {
				return;
			}
			button.addEventListener('click', resetIndicator);
		});

		window.addEventListener('beforeunload', (event) => {
			if (!hasChanges) {
				return;
			}
			const message = 'You have unsaved changes. Are you sure you want to leave?';
			event.returnValue = message;
			return message;
		});
	}

	function initDeviceToggle() {
		const buttons = $$('.nlf-device-btn');
		if (!buttons.length) {
			return;
		}

		buttons.forEach((btn) => {
			btn.addEventListener('click', () => {
				const device = btn.getAttribute('data-device');
				buttons.forEach((button) => button.classList.toggle('active', button === btn));
				$$('.nlf-preview-viewport').forEach((viewport) => {
					viewport.setAttribute('data-device', device);
				});
			});
		});
	}

	function initPreviewRefresh() {
		doc.addEventListener('click', (event) => {
			const trigger = event.target.closest('[data-refresh-preview]');
			if (!trigger) {
				return;
			}
			event.preventDefault();
			const context = trigger.getAttribute('data-refresh-preview') || 'main';
			requestPreview(context, true);
		});
	}

	function initAutoRefreshToggle() {
		$$('.nlf-preview-auto-toggle').forEach((toggle) => {
			const context = toggle.getAttribute('data-preview-auto') || 'main';
			previewState.auto[context] = !!toggle.checked;
		});

		doc.addEventListener('change', (event) => {
			const toggle = event.target.closest('.nlf-preview-auto-toggle');
			if (!toggle) {
				return;
			}

			const context = toggle.getAttribute('data-preview-auto') || 'main';
			previewState.auto[context] = !!toggle.checked;

			if (toggle.checked) {
				requestPreview(context);
			}
		});
	}

	function initHelpTooltips() {
		const triggers = $$('.nlf-help-trigger');
		const helpTexts = $$('.nlf-help-text');

		triggers.forEach((trigger) => {
			trigger.setAttribute('aria-expanded', 'false');
			trigger.addEventListener('click', (event) => {
				event.preventDefault();
				const help = trigger.closest('tr')?.querySelector('.nlf-help-text');
				if (!help) {
					return;
				}

				const isOpen = !help.hasAttribute('hidden');

				helpTexts.forEach((text) => text.setAttribute('hidden', 'true'));
				triggers.forEach((btn) => btn.setAttribute('aria-expanded', 'false'));

				if (!isOpen) {
					help.removeAttribute('hidden');
					trigger.setAttribute('aria-expanded', 'true');
				}
			});
		});
	}

	function initEmptyStateHandlers() {
		doc.addEventListener('click', (event) => {
			if (event.target.closest('.nlf-empty-state .button')) {
				event.preventDefault();
				hideEmptyStates();
			}

			if (event.target.closest('.nlf-onboarding-start')) {
				event.preventDefault();
				hideEmptyStates();
				// Find any "Add Question" button and trigger it
				const addButton = doc.querySelector('.nlf-faq-group-add-row-btn');
				if (addButton) {
					addButton.click();
				}
			}
		});
	}

	function hideEmptyStates() {
		$$('.nlf-empty-state, .nlf-onboarding-card').forEach((element) => {
			element.style.display = 'none';
		});
		const table = $('.nlf-faq-group-table');
		if (table) {
			table.style.display = '';
		}
		requestPreview('main');
	}

	function initQuestionList() {
		const body = $('#nlf-faq-group-questions-body');
		const template = $('#tmpl-nlf-faq-group-row');

		if (!body || !template) {
			return;
		}

		const prepareRow = (row) => {
			row.setAttribute('draggable', 'true');
			row.addEventListener('dragstart', handleDragStart);
			row.addEventListener('dragover', handleDragOver);
			row.addEventListener('drop', handleDrop);
			row.addEventListener('dragend', handleDragEnd);
		};

		$$('.nlf-faq-question-row', body).forEach(prepareRow);

		// Use event delegation to handle clicks on any "Add Question" button
		// This works for both the empty state button and the table footer button
		doc.addEventListener('click', (event) => {
			const addButton = event.target.closest('.nlf-faq-group-add-row-btn');
			if (!addButton) {
				return;
			}

			event.preventDefault();
			
			// Hide empty state when adding first question
			hideEmptyStates();
			
			const index = $$('.nlf-faq-question-row', body).length;
			const html = template.innerHTML.replace(/{{index}}/g, String(index)).trim();
			const tempWrapper = doc.createElement('tbody');
			tempWrapper.innerHTML = html;
			const newRow = tempWrapper.firstElementChild;
			body.appendChild(newRow);
			prepareRow(newRow);
			initNewEditor(newRow);
			renumberGroupCheckboxes();
			doc.dispatchEvent(new CustomEvent('nlf:state-changed'));
			requestPreview('main');
		});

		body.addEventListener('click', (event) => {
			const removeButton = event.target.closest('.nlf-faq-remove-row');
			if (!removeButton) {
				return;
			}

			event.preventDefault();
			const row = removeButton.closest('.nlf-faq-question-row');
			if (!row) {
				return;
			}

			const textarea = row.querySelector('.nlf-faq-group-answer-editor');
			if (textarea && textarea.id) {
				if (window.wp && window.wp.editor && typeof window.wp.editor.remove === 'function') {
					window.wp.editor.remove(textarea.id);
				} else if (window.tinymce) {
					const editor = window.tinymce.get(textarea.id);
					if (editor) {
						editor.remove();
					}
				}
			}

			row.remove();
			renumberGroupCheckboxes();
			doc.dispatchEvent(new CustomEvent('nlf:state-changed'));
			requestPreview('main');
		});
	}

	let dragSource = null;
	let storedEditors = [];

	function handleDragStart(event) {
		dragSource = event.currentTarget;
		dragSource.classList.add('is-dragging');
		event.dataTransfer.effectAllowed = 'move';
		event.dataTransfer.setData('text/plain', '');
		storedEditors = removeEditors(dragSource);
	}

	function handleDragOver(event) {
		event.preventDefault();
		const target = event.currentTarget;
		if (!dragSource || dragSource === target) {
			return;
		}

		const body = target.parentElement;
		const { top, height } = target.getBoundingClientRect();
		const shouldInsertBefore = event.clientY < top + height / 2;

		if (shouldInsertBefore) {
			body.insertBefore(dragSource, target);
		} else {
			body.insertBefore(dragSource, target.nextElementSibling);
		}
	}

	function handleDrop(event) {
		event.preventDefault();
	}

	function handleDragEnd() {
		if (!dragSource) {
			return;
		}
		dragSource.classList.remove('is-dragging');
		restoreEditors(dragSource, storedEditors);
		storedEditors = [];
		dragSource = null;
		renumberGroupCheckboxes();
		doc.dispatchEvent(new CustomEvent('nlf:state-changed'));
		requestPreview('main');
	}

	function removeEditors(row) {
		const editors = [];
		$$('textarea.nlf-faq-group-answer-editor', row).forEach((textarea) => {
			const id = textarea.id;
			if (!id) {
				return;
			}

			let content = textarea.value;

			// Get content from TinyMCE editor if it exists.
			if (window.tinymce) {
				const editor = window.tinymce.get(id);
				if (editor) {
					try {
						content = editor.getContent() || textarea.value;
					} catch (e) {
						content = textarea.value;
					}
					try {
						editor.remove();
					} catch (e) {
						// Editor might be in an invalid state, continue.
					}
				}
			}

			// Remove QuickTags instance if it exists.
			if (window.QTags && window.QTags.instances) {
				delete window.QTags.instances[id];
			}

			// Remove the WordPress editor wrapper entirely so that
			// wp.editor.initialize() can build a fresh one after the drag.
			// Without this the stale wrapper causes a blank visual editor.
			const wrap = doc.getElementById('wp-' + id + '-wrap');
			if (wrap) {
				wrap.parentNode.insertBefore(textarea, wrap);
				wrap.remove();
			}

			// Ensure textarea is visible and holds the saved content.
			textarea.style.display = '';
			textarea.value = content;

			editors.push({ id: id, content: content });
		});
		return editors;
	}

	function restoreEditors(row, editorData) {
		if (!editorData || !editorData.length) {
			return;
		}

		const editorAPI =
			(window.wp && window.wp.oldEditor && typeof window.wp.oldEditor.initialize === 'function')
				? window.wp.oldEditor
				: (window.wp && window.wp.editor && typeof window.wp.editor.initialize === 'function' ? window.wp.editor : null);

		if (!editorAPI) {
			return;
		}

		// Small delay to let the DOM settle after drag.
		setTimeout(() => {
			editorData.forEach((editorInfo) => {
				const id = editorInfo.id;
				const content = editorInfo.content;
				const textarea = doc.getElementById(id);

				if (!textarea) {
					return;
				}

				// Put the saved content in the textarea first; TinyMCE reads
				// the textarea value when it initialises in text (HTML) mode.
				textarea.value = content;

				// Clean up any stale TinyMCE instance that might linger.
				if (window.tinymce && window.tinymce.get(id)) {
					try {
						window.tinymce.get(id).remove();
					} catch (e) {
						// Ignore – instance may already be invalid.
					}
				}

				// Re-initialize the WordPress editor from scratch.
				// Do NOT pass a custom `setup` here – WordPress merges
				// these settings with tinyMCEPreInit.mceInit[id] via
				// Object.assign and a custom `setup` can get lost or
				// override WordPress's own handlers.
				editorAPI.initialize(id, {
					tinymce: { wpautop: true },
					quicktags: true,
					mediaButtons: false,
				});

				// Set the content in the Visual (TinyMCE) editor once it
				// is fully ready.  We attach the listener directly on the
				// instance so it works regardless of how WP merges config.
				(function syncVisualContent() {
					const mce = window.tinymce && window.tinymce.get(id);
					if (!mce) {
						// Editor instance not registered yet – retry.
						setTimeout(syncVisualContent, 50);
						return;
					}
					if (mce.initialized) {
						if (content) {
							mce.setContent(content);
						}
					} else {
						mce.on('init', function () {
							if (content) {
								mce.setContent(content);
							}
						});
					}
				})();
			});
		}, 50);
	}

	// AJAX Save
	function initAjaxSave() {
		const form = $('#nlf-group-edit-form') || $('#post');
		const publishButton = $('#publish');

		if (!form || !publishButton || typeof nlfGroupData === 'undefined') {
			return;
		}

		form.addEventListener('submit', function(e) {
			if (e.submitter && e.submitter.id === 'publish') {
				e.preventDefault();
				handleAjaxSave();
			}
		});

		publishButton.addEventListener('click', function(e) {
			e.preventDefault();
			handleAjaxSave();
		});
	}

	function showTitleError() {
		const titleInput = $('#nlf_group_title');
		if (!titleInput) {
			return;
		}

		titleInput.classList.add('nlf-has-error', 'nlf-shake');
		titleInput.addEventListener('animationend', () => titleInput.classList.remove('nlf-shake'), { once: true });
		titleInput.focus();

		// Add error message if not already shown.
		const wrap = titleInput.closest('#titlediv') || titleInput.parentElement;
		if (!wrap.querySelector('.nlf-field-error')) {
			const msg = doc.createElement('div');
			msg.className = 'nlf-field-error';
			msg.innerHTML = '<span class="dashicons dashicons-warning" aria-hidden="true"></span><span>' +
				(nlfGroupData.i18n?.title_required || 'Please enter a title for this FAQ group.') + '</span>';
			wrap.appendChild(msg);
		}

		// Clear error on input.
		const clearError = () => {
			if (titleInput.value.trim()) {
				titleInput.classList.remove('nlf-has-error');
				const err = wrap.querySelector('.nlf-field-error');
				if (err) {
					err.remove();
				}
				titleInput.removeEventListener('input', clearError);
			}
		};
		titleInput.addEventListener('input', clearError);
	}

	function showInlineNotice(message, type) {
		type = type || 'error';
		// Remove any existing inline notice.
		const existing = $('.nlf-inline-notice');
		if (existing) {
			existing.remove();
		}

		const form = $('#nlf-group-edit-form') || $('#post');
		if (!form) {
			return;
		}

		const icon = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning';
		const notice = doc.createElement('div');
		notice.className = 'nlf-inline-notice nlf-inline-notice--' + type;
		notice.setAttribute('role', 'alert');
		notice.innerHTML = '<span class="dashicons ' + icon + '" aria-hidden="true"></span><p>' + message + '</p>';
		form.insertBefore(notice, form.firstChild);

		// Scroll into view.
		notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

		// Auto-dismiss after 6 seconds.
		setTimeout(() => {
			if (notice.parentNode) {
				notice.style.opacity = '0';
				notice.style.transition = 'opacity 300ms ease';
				setTimeout(() => notice.remove(), 300);
			}
		}, 6000);
	}

	function showHowToUseSidebar(groupId) {
		const box = $('#nlf-how-to-use-box');
		if (!box) {
			return;
		}

		const shortcode = '[krslys_nlf group="' + groupId + '"]';
		const phpCode   = "<?php echo do_shortcode( '" + shortcode + "' ); ?>";

		// Update data-copy-text and visible code for each snippet.
		const snippets = $$('.nlf-htu-snippet', box);
		if (snippets[0]) {
			snippets[0].setAttribute('data-copy-text', shortcode);
			const code = snippets[0].querySelector('.nlf-htu-snippet__code');
			if (code) {
				code.textContent = shortcode;
			}
		}
		if (snippets[1]) {
			snippets[1].setAttribute('data-copy-text', phpCode);
			const code = snippets[1].querySelector('.nlf-htu-snippet__code');
			if (code) {
				code.textContent = phpCode;
			}
		}

		// Reveal the sidebar box.
		box.style.display = '';
	}

	function handleAjaxSave() {
		const form = $('#nlf-group-edit-form') || $('#post');
		const publishButton = $('#publish');

		if (!form || !publishButton || typeof nlfGroupData === 'undefined') {
			return;
		}

		// Client-side title validation.
		const titleInput = $('#nlf_group_title');
		if (titleInput && !titleInput.value.trim()) {
			showTitleError();
			return;
		}

		const originalText = publishButton.value || publishButton.textContent;
		const savingText = nlfGroupData.i18n.saving || 'Saving…';
		const savedText = nlfGroupData.i18n.saved || 'Saved!';

		publishButton.disabled = true;
		publishButton.value = savingText;

		// Sync TinyMCE editors back to their textareas.
		if (window.tinyMCE) {
			window.tinyMCE.triggerSave();
		}

		const formData = new FormData(form);
		formData.append('action', 'nlf_save_faq_group_ajax');
		formData.append('nlf_faq_group_nonce', nlfGroupData.saveNonce);

		// Convert FormData to URLSearchParams for fetch
		const params = new URLSearchParams();
		for (const [key, value] of formData.entries()) {
			params.append(key, value);
		}

		fetch(nlfGroupData.ajaxurl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: params.toString(),
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				publishButton.value = savedText;

				// New group: transition "Add New" → "Edit" in-place (no redirect).
				if (data.data?.group_id && !parseInt(nlfGroupData.groupId, 10)) {
					const newId = data.data.group_id;

					// Update JS state.
					nlfGroupData.groupId = newId;

					// Update hidden form input.
					const hiddenId = form.querySelector('[name="group_id"]');
					if (hiddenId) {
						hiddenId.value = newId;
					}

					// Update URL without reload.
					const newUrl = nlfGroupData.editUrl + '&id=' + newId;
					history.replaceState(null, '', newUrl);

					// Update page heading.
					const heading = doc.querySelector('.wrap > h1.wp-heading-inline');
					if (heading) {
						heading.textContent = nlfGroupData.i18n.edit_title || 'Edit FAQ Group';
					}
					doc.title = doc.title.replace(/Add New FAQ Group/, 'Edit FAQ Group');

					// Reveal and populate "How To Use" sidebar.
					showHowToUseSidebar(newId);

					// Show success notice.
					showInlineNotice(nlfGroupData.i18n.created || 'FAQ group created.', 'success');
				}

				// Notify state collector (debug panel + future consumers).
				doc.dispatchEvent(new CustomEvent('nlf:state-changed'));

				setTimeout(() => {
					publishButton.value = nlfGroupData.i18n.update || 'Update';
					publishButton.disabled = false;
				}, 1500);
			} else {
				showInlineNotice(data.data?.message || 'Error saving FAQ group.');
				publishButton.value = originalText;
				publishButton.disabled = false;
			}
		})
		.catch(error => {
			console.error('Save error:', error);
			showInlineNotice('An unexpected error occurred while saving.');
			publishButton.value = originalText;
			publishButton.disabled = false;
		});
	}

	/**
	 * Copy-to-clipboard for "How To Use" metabox.
	 * The entire snippet button is the click target.
	 */
	function initCopyButtons() {
		$$('.nlf-htu-snippet').forEach(btn => {
			btn.addEventListener('click', () => {
				const text = btn.getAttribute('data-copy-text');
				if (!text) return;

				const write = navigator.clipboard && navigator.clipboard.writeText
					? navigator.clipboard.writeText(text)
					: new Promise(resolve => {
						const ta = doc.createElement('textarea');
						ta.value = text;
						ta.style.cssText = 'position:fixed;opacity:0;';
						doc.body.appendChild(ta);
						ta.select();
						doc.execCommand('copy');
						doc.body.removeChild(ta);
						resolve();
					});

				write.then(() => {
					btn.classList.add('is-copied');
					setTimeout(() => btn.classList.remove('is-copied'), 1500);
				});
			});
		});
	}

	doc.addEventListener('DOMContentLoaded', () => {
		init();
		initCopyButtons();
	});
})();
