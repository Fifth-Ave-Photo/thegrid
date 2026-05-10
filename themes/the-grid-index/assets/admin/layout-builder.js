/* Grid Index Layout Builder — admin behavior */
(function ($) {
	'use strict';

	function reindex($list) {
		$list.children('li').each(function (i) {
			$(this).find('input,select,textarea').each(function () {
				var name = $(this).attr('name');
				if (!name) return;
				$(this).attr('name', name.replace(/gip_lb\[(\d+|__INDEX__)\]/, 'gip_lb[' + i + ']'));
			});
		});
	}

	$(function () {
		var $list = $('#gip-lb-list');

		$list.sortable({
			handle: '.gip-lb__handle',
			placeholder: 'ui-sortable-placeholder',
			tolerance: 'pointer',
			forcePlaceholderSize: true,
			update: function () { reindex($list); }
		}).disableSelection();

		// Row toggle
		$list.on('click', '.gip-lb__toggle', function () {
			$(this).closest('.gip-lb__row').toggleClass('is-open');
			var open = $(this).closest('.gip-lb__row').hasClass('is-open');
			$(this).attr('aria-expanded', open ? 'true' : 'false');
		});

		// Remove
		$list.on('click', '.gip-lb__remove', function () {
			if (!confirm('Remove this section?')) return;
			$(this).closest('.gip-lb__row').slideUp(150, function () {
				$(this).remove();
				reindex($list);
			});
		});

		// Enabled toggle visual
		$list.on('change', '.gip-lb__switch input', function () {
			$(this).closest('.gip-lb__row').toggleClass('is-disabled', !this.checked);
		});

		// Add new section
		$('#gip-lb-add-btn').on('click', function () {
			var type = $('#gip-lb-add-type').val();
			var meta = (window.gipLbTypes && window.gipLbTypes[type]) || { label: type };
			var $tpl = $($('#gip-lb-template').html());
			$tpl.attr('data-type', type);
			$tpl.find('.gip-lb__row-title strong').text(meta.label);
			$tpl.find('.gip-lb__row-desc').text(meta.desc || '');
			$tpl.find('.gip-lb__icon').text(meta.icon || '◇');
			$tpl.find('input,select,textarea').each(function () {
				var name = $(this).attr('name');
				if (name) $(this).attr('name', name.replace('__INDEX__', $list.children().length));
			});
			$tpl.find('input[name$="[type]"]').val(type);
			$tpl.addClass('is-open').hide();
			$list.append($tpl);
			$tpl.slideDown(180, function () { reindex($list); });
		});

		// Viewport toggle
		$('.gip-lb__viewports button[data-vp]').on('click', function () {
			var vp = $(this).data('vp');
			$('.gip-lb__viewports button').removeClass('is-active').attr('aria-pressed', 'false');
			$(this).addClass('is-active').attr('aria-pressed', 'true');
			$('.gip-lb__preview-frame').attr('data-vp', vp);
		});

		// Refresh preview
		$('#gip-lb-refresh').on('click', function () {
			var $f = $('#gip-lb-iframe');
			$f.attr('src', $f.attr('src'));
		});
	});
})(jQuery);
