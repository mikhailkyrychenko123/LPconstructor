(function () {
	'use strict';

	var select = document.getElementById('attribute_type');
	if (!select) {
		return;
	}

	var preview = document.createElement('span');
	preview.className = 'hlpb-attribute-preview';
	preview.setAttribute('aria-live', 'polite');
	preview.setAttribute('aria-label', 'Display type preview');
	select.parentNode.appendChild(preview);

	var previews = {
		'select': '<svg viewBox="0 0 156 42" role="img" aria-hidden="true"><rect x="2" y="3" width="152" height="36" rx="6" fill="#fff" stroke="#c3c4c7"/><path d="m132 17 5 5 5-5" fill="none" stroke="#50575e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 15h57M13 23h35M13 31h75" stroke="#dcdcde" stroke-width="2" stroke-linecap="round"/></svg>',
		'color-image-picker': '<svg viewBox="0 0 156 42" role="img" aria-hidden="true"><rect x="2" y="3" width="152" height="36" rx="6" fill="#fffdf9"/><circle cx="22" cy="21" r="10" fill="#6c2034" stroke="#26384a" stroke-width="2"/><circle cx="54" cy="21" r="9" fill="#d28d91" stroke="#fff" stroke-width="2"/><circle cx="84" cy="21" r="9" fill="#d8c9ae" stroke="#fff" stroke-width="2"/><circle cx="114" cy="21" r="9" fill="#829985" stroke="#fff" stroke-width="2"/></svg>',
		'button': '<svg viewBox="0 0 156 42" role="img" aria-hidden="true"><rect x="2" y="3" width="152" height="36" rx="6" fill="#fffdf9"/><rect x="7" y="9" width="40" height="24" rx="4" fill="#fff" stroke="#d06365"/><rect x="54" y="9" width="42" height="24" rx="4" fill="#fff" stroke="#c3c4c7"/><rect x="103" y="9" width="44" height="24" rx="4" fill="#fff" stroke="#c3c4c7"/><path d="M15 21h24M62 21h26M111 21h28" stroke="#50575e" stroke-width="2" stroke-linecap="round"/></svg>',
		'wc-visual': '<svg viewBox="0 0 156 42" role="img" aria-hidden="true"><circle cx="24" cy="21" r="10" fill="#6c2034"/><circle cx="56" cy="21" r="10" fill="#d28d91"/><circle cx="88" cy="21" r="10" fill="#d8c9ae"/><circle cx="120" cy="21" r="10" fill="#829985"/></svg>'
	};

	function updatePreview() {
		var type = select.value;
		preview.innerHTML = previews[type] || previews.select;
		preview.setAttribute('data-type', type);
	}

	select.addEventListener('change', updatePreview);
	updatePreview();
})();
