(function ($) {
	'use strict';

	function refreshControls(form) {
		$(form).find('.hlpb-variation-control').each(function () {
			var control = this;
			var select = control.querySelector('select');
			if (!select) {
				return;
			}

			var selected = select.value;
			control.querySelectorAll('.hlpb-variation-option').forEach(function (button) {
				var option = Array.prototype.find.call(select.options, function (item) {
					return item.value === button.dataset.value;
				});
				var active = button.dataset.value === selected;

				button.classList.toggle('is-selected', active);
				button.setAttribute('aria-pressed', active ? 'true' : 'false');
				button.disabled = !option || option.disabled;
			});
		});
	}

	$(document).on('click', '.hlpb-variation-option', function () {
		if (this.disabled) {
			return;
		}

		var control = this.closest('.hlpb-variation-control');
		var select = control && control.querySelector('select');
		if (!select) {
			return;
		}

		select.value = this.dataset.value;
		$(select).trigger('change');
		refreshControls(control.closest('form') || control.parentNode);
	});

	$(document).on('change', '.hlpb-variation-control select', function () {
		refreshControls(this.closest('form') || this.parentNode);
	});

	$(document).on(
		'woocommerce_update_variation_values found_variation reset_data hide_variation show_variation',
		'form.variations_form',
		function () {
			refreshControls(this);
		}
	);

	$(function () {
		document.querySelectorAll('form.variations_form').forEach(refreshControls);
	});
})(jQuery);
