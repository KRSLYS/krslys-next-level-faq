(function ($) {
	'use strict';

	function renumberActiveCheckboxes() {
		$('#nlf-faq-questions-body .nlf-faq-question-row').each(function (index) {
			var $row = $(this);
			var $checkbox = $row.find('.nlf-faq-visible-cell input[type="checkbox"]');
			if ($checkbox.length) {
				$checkbox.attr('name', 'nlf_faq_active[' + index + ']');
			}
		});
	}

	$(function () {
		var $body = $('#nlf-faq-questions-body');
		var rowTemplate = $('#tmpl-nlf-faq-row').html();

		$('#nlf-faq-add-row').on('click', function (e) {
			e.preventDefault();

			var index = $body.find('.nlf-faq-question-row').length;
			var html = rowTemplate.replace(/{{index}}/g, String(index));

			$body.append(html);
			renumberActiveCheckboxes();
		});

		$body.on('click', '.nlf-faq-remove-row', function (e) {
			e.preventDefault();
			$(this).closest('.nlf-faq-question-row').remove();
			renumberActiveCheckboxes();
		});

		// Basic drag handle styling – actual drag-and-drop could be added with jQuery UI if desired.
	});
})(jQuery);


