/* Grid Index Theme Options — admin enhancements */
(function ($) {
	'use strict';
	$(function () {
		// Sidebar tabs are PHP-rendered. Each link is a real anchor that reloads
		// the page with ?tab=slug — do NOT intercept clicks (doing so would hide
		// the single PHP-rendered panel and leave the main area blank).

		// Logo media uploader
		$('#gip-upload-logo').on('click', function (e) {
			e.preventDefault();
			var frame = wp.media({ title: 'Select logo', button: { text: 'Use this logo' }, multiple: false });
			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				$('#gip-logo-id').val(att.id);
				$('#gip-logo-preview').html('<img src="' + att.url + '" alt=""/>');
				$('#gip-remove-logo').show();
			});
			frame.open();
		});
		$('#gip-remove-logo').on('click', function (e) {
			e.preventDefault();
			$('#gip-logo-id').val(0);
			$('#gip-logo-preview').empty();
			$(this).hide();
		});

		// Sortable category list
		if ($.fn.sortable) {
			$('#gi-cat-list').sortable({
				handle: '.gi-cat__handle',
				axis: 'y',
				placeholder: 'gi-sort-placeholder',
				forcePlaceholderSize: true
			});
		}

		// Show/hide active state on category rows
		$(document).on('change', '.gi-cat input.gi-cat-on', function () {
			$(this).closest('.gi-cat').toggleClass('is-on', this.checked);
		});

		// Search filter for categories
		$('#gi-cat-search').on('input', function () {
			var q = $(this).val().toLowerCase();
			$('#gi-cat-list .gi-cat').each(function () {
				var hay = ($(this).data('name') + ' ' + $(this).data('slug')).toLowerCase();
				$(this).toggle(!q || hay.indexOf(q) !== -1);
			});
		});

		// Bulk actions
		$('#gi-cat-add-all').on('click', function (e) {
			e.preventDefault();
			$('#gi-cat-list .gi-cat:visible input.gi-cat-on').prop('checked', true).trigger('change');
		});
		$('#gi-cat-hide-empty').on('click', function (e) {
			e.preventDefault();
			$('#gi-cat-list .gi-cat').each(function () {
				if ($(this).data('count') === 0 || $(this).data('count') === '0') {
					$(this).find('input.gi-cat-on').prop('checked', false).trigger('change');
				}
			});
		});
		$('#gi-cat-clear').on('click', function (e) {
			e.preventDefault();
			if (!confirm('Clear all selected homepage sections?')) return;
			$('#gi-cat-list input.gi-cat-on').prop('checked', false).trigger('change');
		});

		// Save bar — submit the form
		$('.gi-savebar [data-save]').on('click', function () {
			$('#gridindex-options-form').trigger('submit');
		});
	});
})(jQuery);
