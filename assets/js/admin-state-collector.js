/**
 * FAQ Group State Collector & Form Populator
 *
 * Two-way binding between nlfGroupData.groupState (JSON) and the form:
 *   1. On load  → populateFormFromState() fills the empty form shell from JSON.
 *   2. On change → collectFormState() reads the form back into a JSON object.
 *
 * The JSON state is the single source of truth.
 */
(() => {
	'use strict';

	const doc  = document;
	const $    = (sel, ctx = doc) => ctx.querySelector(sel);
	const $$   = (sel, ctx = doc) => Array.from(ctx.querySelectorAll(sel));

	/* ================================================================== */
	/*  Helpers                                                            */
	/* ================================================================== */

	function setVal(selector, value, ctx = doc) {
		const el = $(selector, ctx);
		if (el) {
			el.value = value ?? '';
		}
	}

	function setChecked(selector, value, ctx = doc) {
		const el = $(selector, ctx);
		if (el) {
			el.checked = !!value;
		}
	}

	function selectRadio(name, value) {
		const radio = $(`input[name="${name}"][value="${value}"]`);
		if (radio) {
			radio.checked = true;
			// Sync theme UI (is-active class + badge).
			const option = radio.closest('.nlf-theme-option');
			if (option) {
				$$('.nlf-theme-option').forEach(opt => {
					opt.classList.toggle('is-active', opt === option);
					const badge = opt.querySelector('.nlf-theme-badge');
					if (badge) {
						badge.setAttribute('aria-hidden', opt === option ? 'false' : 'true');
					}
				});
			}
		}
	}

	function setColorPicker(selector, value) {
		const el = $(selector);
		if (!el) {
			return;
		}
		el.value = value || '';
		// If wpColorPicker is initialized, sync the widget.
		if (window.jQuery && jQuery.fn.wpColorPicker) {
			try {
				const $el = jQuery(el);
				if ($el.closest('.wp-picker-container').length) {
					$el.wpColorPicker('color', value || '');
				}
			} catch (e) {
				// Color picker not yet initialized — raw value is enough.
			}
		}
	}

	/* ================================================================== */
	/*  Populate: JSON state → form fields                                 */
	/* ================================================================== */

	function populateFormFromState(state) {
		if (!state) {
			return;
		}

		// Title.
		setVal('#nlf_group_title', state.title);

		// ----- Display settings -----
		const ds = state.display_settings || {};
		setChecked('[name="nlf_faq_group_settings[accordion_mode]"]', ds.accordion_mode);
		setVal('[name="nlf_faq_group_settings[initial_state]"]', ds.initial_state || 'all_closed');
		setVal('[name="nlf_faq_group_settings[animation_speed]"]', ds.animation_speed || 'normal');
		setChecked('[name="nlf_faq_group_settings[show_search]"]', ds.show_search);
		setChecked('[name="nlf_faq_group_settings[show_counter]"]', ds.show_counter);
		setChecked('[name="nlf_faq_group_settings[smooth_scroll]"]', ds.smooth_scroll);

		// ----- Theme -----
		const ts = state.theme_settings || {};
		selectRadio('nlf_faq_group_theme', ts.theme || 'default');

		const cc = ts.custom_colors || {};
		setColorPicker('#theme_custom_primary', cc.primary);
		setColorPicker('#theme_custom_secondary', cc.secondary);
		setColorPicker('#theme_custom_accent', cc.accent);
		setColorPicker('#theme_custom_background', cc.background);

		// ----- Custom styles -----
		setChecked('[name="nlf_faq_group_use_custom_style"]', state.use_custom_style);

		// Show/hide the custom style fields panel.
		const customFieldsPanel = $('.nlf-custom-style-fields');
		if (customFieldsPanel) {
			customFieldsPanel.style.display = state.use_custom_style ? '' : 'none';
		}

		const cs = state.custom_styles || {};
		Object.entries(cs).forEach(([key, val]) => {
			const sel = `[name="nlf_faq_group_custom_styles[${key}]"]`;
			const el = $(sel);
			if (!el) {
				return;
			}
			if (el.classList.contains('nlf-color-picker')) {
				setColorPicker(sel, val);
			} else if (el.tagName === 'SELECT') {
				setVal(sel, val);
			} else {
				setVal(sel, val);
			}
		});

		// ----- Items (only populate if PHP didn't already render rows) -----
		const body = $('#nlf-faq-group-questions-body');
		const existingRows = body ? $$('.nlf-faq-question-row', body) : [];

		if (existingRows.length === 0 && state.items && state.items.length > 0) {
			populateItems(state.items);
		}
	}

	/**
	 * Create item rows from state (for new groups or when PHP didn't render them).
	 */
	function populateItems(items) {
		const body     = $('#nlf-faq-group-questions-body');
		const template = $('#tmpl-nlf-faq-group-row');
		if (!body || !template) {
			return;
		}

		// Show table, hide empty state.
		const table = body.closest('table');
		if (table) {
			table.style.display = '';
		}
		$$('.nlf-empty-state, .nlf-onboarding-card').forEach(el => {
			el.style.display = 'none';
		});

		const newRows = [];

		items.forEach((item, i) => {
			const html = template.innerHTML.replace(/\{\{index\}\}/g, String(i)).trim();
			const wrapper = doc.createElement('tbody');
			wrapper.innerHTML = html;
			const row = wrapper.firstElementChild;
			body.appendChild(row);
			newRows.push(row);

			// Fill in values.
			setVal('[name="nlf_faq_group_item_id[]"]', item.id || '', row);
			setVal('[name="nlf_faq_group_question[]"]', item.question || '', row);

			// Answer — textarea value; nlfSetupItemRow will initialize TinyMCE.
			const textarea = row.querySelector('.nlf-faq-group-answer-editor');
			if (textarea) {
				textarea.value = item.answer || '';
			}

			// Checkboxes.
			setChecked(`[name="nlf_faq_group_visible[${i}]"]`, item.status, row);
			setChecked(`[name="nlf_faq_group_open[${i}]"]`, item.initial_state, row);
			setChecked(`[name="nlf_faq_group_highlight[${i}]"]`, item.highlight, row);
		});

		// Attach drag handlers and initialize editors via the metabox script.
		// Guard against script load order: if metabox has already run, call now;
		// otherwise wait for it to signal readiness via nlf:metabox-ready.
		const setupAll = () => newRows.forEach((row) => window.nlfSetupItemRow(row));

		if (typeof window.nlfSetupItemRow === 'function') {
			setupAll();
		} else {
			doc.addEventListener('nlf:metabox-ready', setupAll, { once: true });
		}
	}

	/* ================================================================== */
	/*  Collect: form fields → JSON state                                  */
	/* ================================================================== */

	function collectFormState() {
		const form = $('#nlf-group-edit-form') || $('#post');
		if (!form) {
			return null;
		}

		// Title.
		const title = ($('#nlf_group_title') || {}).value || '';

		// Items.
		const items = $$('.nlf-faq-question-row', form).map((row, i) => {
			const textarea = row.querySelector('.nlf-faq-group-answer-editor');
			let answer = '';

			if (textarea) {
				const editor = window.tinymce && textarea.id && window.tinymce.get(textarea.id);
				if (editor) {
					// Fall back to textarea.value when the editor is in Text/Code mode
					// and getContent() hasn't synced the user's typed content yet.
					try {
						answer = editor.getContent() || textarea.value;
					} catch (e) {
						answer = textarea.value;
					}
				} else {
					answer = textarea.value;
				}
			}

			return {
				id:            parseInt(row.querySelector('[name="nlf_faq_group_item_id[]"]')?.value, 10) || 0,
				question:      row.querySelector('[name="nlf_faq_group_question[]"]')?.value || '',
				answer:        answer,
				status:        row.querySelector('[name="nlf_faq_group_visible[' + i + ']"]')?.checked ? 1 : 0,
				position:      i,
				initial_state: row.querySelector('[name="nlf_faq_group_open[' + i + ']"]')?.checked ? 1 : 0,
				highlight:     row.querySelector('[name="nlf_faq_group_highlight[' + i + ']"]')?.checked ? 1 : 0,
			};
		});

		// Theme.
		const themeRadio = $('input[name="nlf_faq_group_theme"]:checked', form);
		const theme_settings = {
			theme: themeRadio ? themeRadio.value : 'default',
			custom_colors: {
				primary:    $('[name="nlf_faq_group_theme_custom[primary]"]', form)?.value || '',
				secondary:  $('[name="nlf_faq_group_theme_custom[secondary]"]', form)?.value || '',
				accent:     $('[name="nlf_faq_group_theme_custom[accent]"]', form)?.value || '',
				background: $('[name="nlf_faq_group_theme_custom[background]"]', form)?.value || '',
			},
		};

		// Display settings.
		const display_settings = {
			accordion_mode:  !!$('[name="nlf_faq_group_settings[accordion_mode]"]', form)?.checked,
			initial_state:   $('[name="nlf_faq_group_settings[initial_state]"]', form)?.value || 'all_closed',
			animation_speed: $('[name="nlf_faq_group_settings[animation_speed]"]', form)?.value || 'normal',
			show_search:     !!$('[name="nlf_faq_group_settings[show_search]"]', form)?.checked,
			show_counter:    !!$('[name="nlf_faq_group_settings[show_counter]"]', form)?.checked,
			smooth_scroll:   !!$('[name="nlf_faq_group_settings[smooth_scroll]"]', form)?.checked,
		};

		// Custom styles.
		const use_custom_style = !!$('[name="nlf_faq_group_use_custom_style"]', form)?.checked;
		const custom_styles = {};
		$$('[name^="nlf_faq_group_custom_styles["]', form).forEach(el => {
			const match = el.name.match(/\[([^\]]+)\]/);
			if (match) {
				custom_styles[match[1]] = el.value;
			}
		});

		return {
			id: (typeof nlfGroupData !== 'undefined' && nlfGroupData.groupId) || 0,
			title,
			theme_settings,
			display_settings,
			custom_styles,
			use_custom_style,
			items,
		};
	}

	/* ================================================================== */
	/*  Debug panel: show / hide / update / copy                           */
	/* ================================================================== */

	function updateDebugPanel() {
		const output = $('#nlf-json-state-output');
		if (!output) {
			return;
		}
		const state = collectFormState();
		if (state) {
			output.value = JSON.stringify(state, null, 2);
		}
	}

	function initDebugPanel() {
		const toggle  = $('#nlf-show-json-state');
		const output  = $('#nlf-json-state-output');
		const copyBtn = $('#nlf-copy-json');

		if (!toggle || !output) {
			return;
		}

		// Show / hide.
		toggle.addEventListener('change', function () {
			const show = this.checked;
			output.style.display  = show ? 'block' : 'none';
			if (copyBtn) {
				copyBtn.style.display = show ? 'inline-block' : 'none';
			}
			if (show) {
				updateDebugPanel();
			}
		});

		// Copy button.
		if (copyBtn) {
			copyBtn.addEventListener('click', () => {
				output.select();
				navigator.clipboard.writeText(output.value);
			});
		}
	}

	/* ================================================================== */
	/*  Live sync: keep debug panel in sync with form changes              */
	/* ================================================================== */

	function initLiveSync() {
		const form = $('#nlf-group-edit-form') || $('#post');

		if (!form) {
			return;
		}

		let timer;
		const schedule = () => {
			clearTimeout(timer);
			// Only write to the debug textarea if it exists (WP_DEBUG mode).
			if (!$('#nlf-json-state-output')) {
				return;
			}
			timer = setTimeout(updateDebugPanel, 300);
		};

		// Standard DOM events (covers inputs, selects, checkboxes, radios).
		form.addEventListener('input', schedule, true);
		form.addEventListener('change', schedule, true);

		// TinyMCE editors — attach listeners as they are created.
		if (window.tinymce) {
			window.tinymce.on('AddEditor', (e) => {
				e.editor.on('input change keyup', schedule);
			});
		}

		// Custom event dispatched by the metabox script after programmatic
		// changes (add / remove question, drag-and-drop reorder, AJAX save).
		doc.addEventListener('nlf:state-changed', schedule);
	}

	/* ================================================================== */
	/*  Bootstrap                                                          */
	/* ================================================================== */

	// Expose globally for other scripts and browser console.
	window.nlfCollectFormState    = collectFormState;
	window.nlfPopulateForm        = populateFormFromState;

	doc.addEventListener('DOMContentLoaded', () => {
		// Populate form from the server-provided state.
		if (typeof nlfGroupData !== 'undefined' && nlfGroupData.groupState) {
			populateFormFromState(nlfGroupData.groupState);
		}

		initDebugPanel();
		initLiveSync();

		// Initial debug panel content.
		updateDebugPanel();
	});
})();
