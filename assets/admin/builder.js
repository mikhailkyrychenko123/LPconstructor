(function ($) {
	'use strict';

	var $list = $('#hlpb-section-list');
	var $status = $('.hlpb-toolbar-status');
	if (!$list.length || typeof HLPBBuilder === 'undefined') {
		return;
	}

	function cards() {
		return $list.children('.hlpb-section-card');
	}

	function refreshTypeOptions() {
		var used = {};
		var singleInstanceTypes = HLPBBuilder.singleInstanceTypes || ['header', 'cart_section', 'footer'];
		cards().each(function () {
			var type = $(this).data('section-type');
			if ($.inArray(type, singleInstanceTypes) !== -1) {
				used[type] = true;
			}
		});
		$('#hlpb-new-section-type option').each(function () {
			$(this).prop('disabled', $.inArray(this.value, singleInstanceTypes) !== -1 && !!used[this.value]);
		});
		var $typeSelect = $('#hlpb-new-section-type');
		if ($typeSelect.find(':selected').prop('disabled')) {
			$typeSelect.find('option:not(:disabled)').first().prop('selected', true);
		}
		updateTypePreview();
	}

	function updateNumbers() {
		cards().each(function (index) {
			$(this).find('.hlpb-order').val(index + 1);
		});
		refreshTypeOptions();
	}

	function sortByEnteredNumbers() {
		var ordered = cards().get().sort(function (left, right) {
			var leftOrder = parseInt($(left).find('.hlpb-order').val(), 10) || 999999;
			var rightOrder = parseInt($(right).find('.hlpb-order').val(), 10) || 999999;
			return leftOrder - rightOrder;
		});
		$.each(ordered, function (_, card) {
			$list.append(card);
		});
		updateNumbers();
	}

	$list.sortable({
		handle: '.hlpb-drag-handle',
		items: '> .hlpb-section-card',
		placeholder: 'hlpb-section-placeholder',
		forcePlaceholderSize: true,
		update: updateNumbers
	});

	$list.on('click', '.hlpb-remove-section', function () {
		$(this).closest('.hlpb-section-card').remove();
		updateNumbers();
	});

	$list.on('click', '.hlpb-toggle-section', function () {
		var $button = $(this);
		var $card = $button.closest('.hlpb-section-card');
		var collapsed = !$card.hasClass('is-collapsed');
		$card.toggleClass('is-collapsed', collapsed);
		$button.attr('aria-expanded', collapsed ? 'false' : 'true');
		$button.text(collapsed ? HLPBBuilder.expandSection : HLPBBuilder.collapseSection);
	});

	$list.on('click', '.hlpb-clone-section', function () {
		var $source = $(this).closest('.hlpb-section-card');
		var singleInstanceTypes = HLPBBuilder.singleInstanceTypes || ['header', 'cart_section', 'footer'];
		if ($.inArray($source.data('section-type'), singleInstanceTypes) !== -1) {
			return;
		}
		var oldId = String($source.attr('data-section-id') || '');
		var newId = 'section_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 9);
		var $clone = $source.clone();

		$clone.attr('data-section-id', newId).removeData('section-id');
		$clone.find('[name]').each(function () {
			var oldName = $(this).attr('name');
			if (oldName) {
				$(this).attr('name', oldName.replace('hlpb_sections[' + oldId + ']', 'hlpb_sections[' + newId + ']'));
				if (/\[id\]$/.test(oldName)) {
					$(this).val(newId);
				}
			}
		});
		$clone.find('[id]').each(function () {
			this.id = this.id.replace(oldId, newId);
		});
		$clone.find('[for]').each(function () {
			$(this).attr('for', $(this).attr('for').replace(oldId, newId));
		});

		$source.after($clone);
		updateNumbers();
		$clone.find('.hlpb-clone-section').trigger('focus');
	});

	$list.on('change', '.hlpb-order', sortByEnteredNumbers);

	$list.on('click', '.hlpb-select-image:not([data-image-target="logo"])', function () {
		var $card = $(this).closest('.hlpb-section-card');
		var frame = wp.media({
			title: $(this).text(),
			button: { text: $(this).text() },
			multiple: false,
			library: { type: 'image' }
		});
		frame.on('select', function () {
			var image = frame.state().get('selection').first().toJSON();
			$card.find('.hlpb-image-id').val(image.id);
			$card.find('.hlpb-image-preview').attr('src', image.sizes && image.sizes.large ? image.sizes.large.url : image.url);
		});
		frame.open();
	});

	$list.on('click', '.hlpb-clear-image', function () {
		var $card = $(this).closest('.hlpb-section-card');
		$card.find('.hlpb-image-id').val('0');
		$card.find('.hlpb-image-preview').attr('src', HLPBBuilder.imageBase + $card.find('.hlpb-demo-image').val());
	});

	$list.on('click', '.hlpb-select-image[data-image-target="logo"]', function () {
		var $card = $(this).closest('.hlpb-section-card');
		var frame = wp.media({
			title: $(this).text(),
			button: { text: $(this).text() },
			multiple: false,
			library: { type: 'image' }
		});
		frame.on('select', function () {
			var image = frame.state().get('selection').first().toJSON();
			$card.find('.hlpb-logo-id').val(image.id);
			$card.find('.hlpb-logo-preview').attr('src', image.sizes && image.sizes.medium ? image.sizes.medium.url : image.url);
		});
		frame.open();
	});

	$list.on('click', '.hlpb-clear-logo', function () {
		var $card = $(this).closest('.hlpb-section-card');
		var template = $('#hlpb-template-select').val() || 'cozy-mix';
		$card.find('.hlpb-logo-id').val('0');
		$card.find('.hlpb-logo-preview').attr('src', HLPBBuilder.pluginUrl + 'templates/' + template + '/assets/icons/nest-stack.svg');
	});

	function updateMenuMode($editor) {
		var custom = $editor.find('.hlpb-menu-mode').val() === 'custom';
		$editor.find('.hlpb-menu-custom').prop('hidden', !custom);
		$editor.find('.hlpb-menu-wordpress').prop('hidden', custom);
	}

	$list.on('change', '.hlpb-menu-mode', function () {
		updateMenuMode($(this).closest('.hlpb-menu-editor'));
	});

	function reindexMenuItems($editor) {
		var side = $editor.data('menu-side');
		var sectionId = $editor.closest('.hlpb-section-card').data('section-id');
		$editor.find('.hlpb-menu-item-row').each(function (index) {
			$(this).find('input').eq(0).attr('name', 'hlpb_sections[' + sectionId + '][' + side + '_menu_items][' + index + '][label]');
			$(this).find('input').eq(1).attr('name', 'hlpb_sections[' + sectionId + '][' + side + '_menu_items][' + index + '][url]');
		});
	}

	$list.on('click', '.hlpb-add-menu-item', function () {
		var $editor = $(this).closest('.hlpb-menu-editor');
		var side = $editor.data('menu-side');
		var $rows = $editor.find('.hlpb-menu-item-row');
		if ($rows.length >= 20) {
			return;
		}
		var sectionId = $editor.closest('.hlpb-section-card').data('section-id');
		var index = $rows.length;
		var row = document.createElement('div');
		row.className = 'hlpb-menu-item-row';
		[
			{ label: HLPBBuilder.menuItemLabel, suffix: '[label]', inputmode: '' },
			{ label: HLPBBuilder.menuLinkLabel, suffix: '[url]', inputmode: 'url' }
		].forEach(function (field) {
			var label = document.createElement('label');
			var caption = document.createElement('span');
			caption.textContent = field.label;
			var input = document.createElement('input');
			input.type = 'text';
			input.name = 'hlpb_sections[' + sectionId + '][' + side + '_menu_items][' + index + ']' + field.suffix;
			if (field.inputmode) {
				input.setAttribute('inputmode', field.inputmode);
			}
			label.appendChild(caption);
			label.appendChild(input);
			row.appendChild(label);
		});
		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'button-link-delete hlpb-remove-menu-item';
		remove.setAttribute('aria-label', HLPBBuilder.removeMenuItem);
		remove.textContent = '×';
		row.appendChild(remove);
		$editor.find('.hlpb-menu-item-list').append(row);
		$(row).find('input').first().trigger('focus');
	});

	$list.on('click', '.hlpb-remove-menu-item', function () {
		var $editor = $(this).closest('.hlpb-menu-editor');
		$(this).closest('.hlpb-menu-item-row').remove();
		reindexMenuItems($editor);
	});

	$('#hlpb-template-select').on('change', function () {
		var template = this.value;
		var $option = $(this).find(':selected');
		HLPBBuilder.imageBase = $option.data('image-base') || (HLPBBuilder.pluginUrl + 'templates/' + template + '/assets/images/');
		HLPBBuilder.defaultImage = $option.data('default-image') || HLPBBuilder.defaultImage;
		$list.find('.hlpb-section-card[data-section-type="text_image"]').each(function () {
			var $card = $(this);
			if (!$card.find('.hlpb-image-id').val() || $card.find('.hlpb-image-id').val() === '0') {
				$card.find('.hlpb-demo-image').val(HLPBBuilder.defaultImage);
				$card.find('.hlpb-image-preview').attr('src', HLPBBuilder.imageBase + HLPBBuilder.defaultImage);
			}
		});
	});

	function updateTypePreview() {
		var type = $('#hlpb-new-section-type').val();
		var $preview = $('.hlpb-section-type-preview');
		if (!$preview.length || !HLPBBuilder.previews || !HLPBBuilder.previews[type]) {
			return;
		}
		$preview.find('.hlpb-section-type-preview-art').html(HLPBBuilder.previews[type]);
		$preview.find('.hlpb-section-type-preview-label').text(HLPBBuilder.labels[type] || '');
	}

	$('#hlpb-new-section-type').on('change', updateTypePreview);

	$list.on('input change', '.hlpb-color-field input[type=color]', function () {
		$(this).siblings('span').text(this.value);
	});

	function makeFeatureRow(sectionId, index) {
		var row = document.createElement('div');
		row.className = 'hlpb-feature-row';
		var iconLabel = document.createElement('label');
		var iconCaption = document.createElement('span');
		iconCaption.textContent = HLPBBuilder.iconLabel;
		var iconSelect = document.createElement('select');
		iconSelect.name = 'hlpb_sections[' + sectionId + '][features][' + index + '][icon]';
		Object.keys(HLPBBuilder.icons).forEach(function (value) {
			var option = document.createElement('option');
			option.value = value;
			option.textContent = HLPBBuilder.icons[value];
			if (value === 'heart') {
				option.selected = true;
			}
			iconSelect.appendChild(option);
		});
		iconLabel.appendChild(iconCaption);
		iconLabel.appendChild(iconSelect);

		var textLabel = document.createElement('label');
		var textCaption = document.createElement('span');
		textCaption.textContent = HLPBBuilder.captionLabel;
		var textInput = document.createElement('input');
		textInput.type = 'text';
		textInput.name = 'hlpb_sections[' + sectionId + '][features][' + index + '][text]';
		textLabel.appendChild(textCaption);
		textLabel.appendChild(textInput);

		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'button-link-delete hlpb-remove-feature';
		remove.setAttribute('aria-label', HLPBBuilder.removeIcon);
		remove.textContent = '×';
		row.appendChild(iconLabel);
		row.appendChild(textLabel);
		row.appendChild(remove);
		return row;
	}

	function reindexFeatures($card) {
		var sectionId = $card.data('section-id');
		$card.find('.hlpb-feature-row').each(function (index) {
			$(this).find('select').attr('name', 'hlpb_sections[' + sectionId + '][features][' + index + '][icon]');
			$(this).find('input').attr('name', 'hlpb_sections[' + sectionId + '][features][' + index + '][text]');
		});
	}

	$list.on('click', '.hlpb-add-feature', function () {
		var $card = $(this).closest('.hlpb-section-card');
		var $rows = $card.find('.hlpb-feature-row');
		if ($rows.length >= 8) {
			return;
		}
		$card.find('.hlpb-feature-list').append(makeFeatureRow($card.data('section-id'), $rows.length));
	});

	function makeMixBenefitRow(sectionId, index) {
		var row = document.createElement('div');
		row.className = 'hlpb-mix-benefit-row';
		var controls = document.createElement('div');
		controls.className = 'hlpb-mix-benefit-controls';
		var iconLabel = document.createElement('label');
		var iconCaption = document.createElement('span');
		iconCaption.textContent = HLPBBuilder.iconLabel;
		var iconSelect = document.createElement('select');
		iconSelect.name = 'hlpb_sections[' + sectionId + '][benefits][' + index + '][icon]';
		Object.keys(HLPBBuilder.icons).forEach(function (value) {
			var option = document.createElement('option');
			option.value = value;
			option.textContent = HLPBBuilder.icons[value];
			iconSelect.appendChild(option);
		});
		iconLabel.appendChild(iconCaption);
		iconLabel.appendChild(iconSelect);
		var titleLabel = document.createElement('label');
		var titleCaption = document.createElement('span');
		titleCaption.textContent = HLPBBuilder.titleLabel;
		var titleInput = document.createElement('input');
		titleInput.type = 'text';
		titleInput.name = 'hlpb_sections[' + sectionId + '][benefits][' + index + '][title]';
		titleLabel.appendChild(titleCaption);
		titleLabel.appendChild(titleInput);
		var colorLabel = document.createElement('label');
		colorLabel.className = 'hlpb-field hlpb-color-field';
		var colorCaption = document.createElement('span');
		colorCaption.textContent = HLPBBuilder.iconColorLabel;
		var colorInput = document.createElement('input');
		colorInput.type = 'color';
		colorInput.name = 'hlpb_sections[' + sectionId + '][benefits][' + index + '][icon_color]';
		colorInput.value = '#244c59';
		var colorValue = document.createElement('span');
		colorValue.textContent = '#244c59';
		colorLabel.appendChild(colorCaption);
		colorLabel.appendChild(colorInput);
		colorLabel.appendChild(colorValue);
		var textLabel = document.createElement('label');
		textLabel.className = 'hlpb-mix-benefit-text';
		var textCaption = document.createElement('span');
		textCaption.textContent = HLPBBuilder.textLabel;
		var textArea = document.createElement('textarea');
		textArea.rows = 2;
		textArea.name = 'hlpb_sections[' + sectionId + '][benefits][' + index + '][text]';
		textLabel.appendChild(textCaption);
		textLabel.appendChild(textArea);
		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'button-link-delete hlpb-remove-mix-benefit';
		remove.setAttribute('aria-label', HLPBBuilder.removeBenefit);
		remove.textContent = '×';
		controls.appendChild(iconLabel);
		controls.appendChild(titleLabel);
		controls.appendChild(colorLabel);
		controls.appendChild(textLabel);
		controls.appendChild(remove);
		row.appendChild(controls);
		return row;
	}

	function reindexMixBenefits($card) {
		var sectionId = $card.data('section-id');
		$card.find('.hlpb-mix-benefit-row').each(function (index) {
			$(this).find('[name]').each(function () {
				var name = $(this).attr('name');
				$(this).attr('name', name.replace(/\[benefits\]\[\d+\]/, '[benefits][' + index + ']'));
			});
		});
	}

	$list.on('click', '.hlpb-add-mix-benefit', function () {
		var $card = $(this).closest('.hlpb-section-card');
		var $rows = $card.find('.hlpb-mix-benefit-row');
		if ($rows.length >= 8) {
			return;
		}
		$card.find('.hlpb-mix-benefit-list').append(makeMixBenefitRow($card.data('section-id'), $rows.length));
	});

	$list.on('click', '.hlpb-remove-mix-benefit', function () {
		var $card = $(this).closest('.hlpb-section-card');
		$(this).closest('.hlpb-mix-benefit-row').remove();
		reindexMixBenefits($card);
	});

	$list.on('click', '.hlpb-remove-feature', function () {
		var $card = $(this).closest('.hlpb-section-card');
		$(this).closest('.hlpb-feature-row').remove();
		reindexFeatures($card);
	});

	$('#hlpb-add-section').on('click', function () {
		var type = $('#hlpb-new-section-type').val();
		var $button = $(this);
		var data = new FormData();
		data.append('action', 'hlpb_render_section');
		data.append('nonce', HLPBBuilder.nonce);
		data.append('type', type);
		data.append('template', $('#hlpb-template-select').val());
		$button.prop('disabled', true);
		$status.text('');
		fetch(HLPBBuilder.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (response) { return response.json(); })
			.then(function (response) {
				if (!response.success || !response.data || !response.data.html) {
					throw new Error(HLPBBuilder.error);
				}
				$list.append(response.data.html);
				updateNumbers();
				$list.find('.hlpb-section-card').last().find('.hlpb-section-title').trigger('focus');
			})
			.catch(function () { $status.text(HLPBBuilder.error); })
			.finally(function () { $button.prop('disabled', false); });
	});

	updateNumbers();
	updateTypePreview();
})(jQuery);
