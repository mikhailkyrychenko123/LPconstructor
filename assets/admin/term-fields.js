(function ($) {
	'use strict';

	$(function () {
		var $colorText = $('#hlpb-term-color');
		var $colorPicker = $('#hlpb-term-color-picker');
		$colorPicker.on('input change', function () {
			$colorText.val(this.value.toUpperCase());
		});
		$colorText.on('input change', function () {
			var value = this.value.trim();
			if (/^#[0-9a-fA-F]{6}$/.test(value)) {
				$colorPicker.val(value);
			}
		});

		var frame;
		$('.hlpb-term-select-image').on('click', function (event) {
			event.preventDefault();
			var $control = $(this).closest('.hlpb-term-image-control');
			frame = wp.media({
				title: $(this).text(),
				button: { text: $(this).text() },
				multiple: false,
				library: { type: 'image' }
			});
			frame.on('select', function () {
				var image = frame.state().get('selection').first().toJSON();
				$control.find('input[type=hidden]').val(image.id);
				$control.find('.hlpb-term-image-preview').attr('src', image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url).prop('hidden', false);
				$control.find('.hlpb-term-remove-image').prop('hidden', false);
			});
			frame.open();
		});

		$('.hlpb-term-remove-image').on('click', function (event) {
			event.preventDefault();
			var $control = $(this).closest('.hlpb-term-image-control');
			$control.find('input[type=hidden]').val('0');
			$control.find('.hlpb-term-image-preview').attr('src', '').prop('hidden', true);
			$(this).prop('hidden', true);
		});
	});
})(jQuery);
