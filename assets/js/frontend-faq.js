(function () {
	'use strict';

	var analyticsConfig = (typeof window !== 'undefined' && window.nlfFaqData) ? window.nlfFaqData : null;
	if (analyticsConfig && analyticsConfig.tracking === false) {
		analyticsConfig = null;
	}

	function toggleItem(item, container) {
		if (!item) {
			return;
		}

		var question = item.querySelector('.nlf-faq__question');
		var isOpen = item.classList.contains('is-open');
		var isAccordion = container && container.dataset.accordion === '1';

		// If accordion mode, close all other items and update their aria state.
		if (!isOpen && isAccordion) {
			var allItems = container.querySelectorAll('.nlf-faq__item.is-open');
			for (var i = 0; i < allItems.length; i++) {
				if (allItems[i] !== item) {
					allItems[i].classList.remove('is-open');
					var otherQuestion = allItems[i].querySelector('.nlf-faq__question');
					if (otherQuestion) {
						otherQuestion.setAttribute('aria-expanded', 'false');
					}
				}
			}
		}

		if (isOpen) {
			item.classList.remove('is-open');
			if (question) {
				question.setAttribute('aria-expanded', 'false');
			}
			trackAnalytics(container, item, 'close');
		} else {
			item.classList.add('is-open');
			if (question) {
				question.setAttribute('aria-expanded', 'true');
			}
			trackAnalytics(container, item, 'open');
		}

		// Smooth scroll if enabled.
		if (!isOpen && container && container.dataset.smoothScroll === '1') {
			setTimeout(function() {
				item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			}, 100);
		}
	}

	function initSearch(container) {
		var searchInput = container.querySelector('.nlf-faq-search-input');
		if (!searchInput) {
			return;
		}

		searchInput.addEventListener('input', function (e) {
			var query = e.target.value.toLowerCase().trim();
			var items = container.querySelectorAll('.nlf-faq__item');

			for (var i = 0; i < items.length; i++) {
				var item = items[i];
				// Select the question text span, excluding counter and icon spans
				var question = item.querySelector('.nlf-faq__question > span:not(.nlf-faq__counter):not(.nlf-faq__icon)');
				var answer = item.querySelector('.nlf-faq__answer');
				
				var questionText = question ? question.textContent.toLowerCase() : '';
				var answerText = answer ? answer.textContent.toLowerCase() : '';
				
				if (query === '' || questionText.indexOf(query) !== -1 || answerText.indexOf(query) !== -1) {
					item.style.display = '';
				} else {
					item.style.display = 'none';
				}
			}
		});
	}

	function applyAnimationSpeed(container) {
		var speed = container.dataset.animationSpeed || 'normal';
		var durationMs;

		switch (speed) {
			case 'fast':
				durationMs = 150;
				break;
			case 'slow':
				durationMs = 500;
				break;
			default:
				durationMs = 300;
				break;
		}

		var dur = durationMs + 'ms';
		container.style.setProperty('--nlf-faq-transition', dur + ' ease');
		container.style.setProperty('--nlf-faq-answer-transition', 'max-height ' + dur + ' ease, opacity ' + dur + ' ease, transform ' + dur + ' ease');
	}

	function bindFaq(container) {
		if (!container) {
			return;
		}

		// Apply animation speed from data attribute.
		applyAnimationSpeed(container);

		// Set up accessibility attributes on question elements.
		var questions = container.querySelectorAll('.nlf-faq__question');
		for (var q = 0; q < questions.length; q++) {
			var questionEl = questions[q];
			questionEl.setAttribute('role', 'button');
			questionEl.setAttribute('tabindex', '0');
			var parentItem = questionEl.closest('.nlf-faq__item');
			var isOpen = parentItem && parentItem.classList.contains('is-open');
			questionEl.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		}

		// Handle click events.
		container.addEventListener('click', function (event) {
			var target = event.target;

			// Find question wrapper.
			while (target && target !== container) {
				if (target.classList && target.classList.contains('nlf-faq__question')) {
					var item = target.closest('.nlf-faq__item');
					toggleItem(item, container);
					break;
				}
				target = target.parentNode;
			}
		});

		// Handle keyboard events (Enter and Space).
		container.addEventListener('keydown', function (event) {
			if (event.key !== 'Enter' && event.key !== ' ') {
				return;
			}

			var target = event.target;
			if (target && target.classList && target.classList.contains('nlf-faq__question')) {
				event.preventDefault();
				var item = target.closest('.nlf-faq__item');
				toggleItem(item, container);
			}
		});

		// Initialize search if present.
		initSearch(container);
	}

	function initFaq(context) {
		var containers = (context || document).querySelectorAll('.nlf-faq');
		for (var i = 0; i < containers.length; i++) {
			bindFaq(containers[i]);
		}
	}

	// Export for use in admin live preview
	if (typeof window !== 'undefined') {
		window.nlfInitFaq = initFaq;
	}

	document.addEventListener('DOMContentLoaded', function () {
		initFaq();
	});

	function trackAnalytics(container, item, action) {
		if (!analyticsConfig) {
			return;
		}

		var groupId = container ? container.getAttribute('data-group-id') : null;
		var questionId = item ? item.getAttribute('data-faq-id') : null;

		if (!groupId || !questionId) {
			return;
		}

		var payload = {
			groupId: parseInt(groupId, 10),
			questionId: parseInt(questionId, 10),
			action: action,
		};

		if (typeof window.nlfFaqAnalytics === 'function') {
			try {
				window.nlfFaqAnalytics(payload);
			} catch (error) {
				// no-op
			}
		}

		if (Array.isArray(window.dataLayer)) {
			window.dataLayer.push({
				event: 'nlfFaqInteraction',
				nlfFaq: payload,
			});
		}

		if (action === 'open') {
			sendAnalyticsBeacon(payload);
		}
	}

	function sendAnalyticsBeacon(payload) {
		if (!analyticsConfig || !analyticsConfig.ajaxurl || !analyticsConfig.nonce) {
			return;
		}

		var params = new URLSearchParams();
		params.append('action', 'nlf_faq_track');
		params.append('group_id', String(payload.groupId));
		params.append('question_id', String(payload.questionId));
		params.append('state', payload.action);
		params.append('nonce', analyticsConfig.nonce);

		if (navigator.sendBeacon) {
			navigator.sendBeacon(analyticsConfig.ajaxurl, params);
			return;
		}

		fetch(analyticsConfig.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: params,
		});
	}
})();


