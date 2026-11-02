(function () {
	'use strict';

	var landing = document.getElementById('hlpb-shortcode-landing');
	var gallery = document.getElementById('hlpb-shortcode-gallery');
	var output = document.getElementById('hlpb-shortcode-value');
	var button = document.getElementById('hlpb-shortcode-copy');
	var status = document.getElementById('hlpb-shortcode-copy-status');

	function update() {
		if (landing && gallery && output) {
			output.value = '[hlpb_cart landing="' + landing.value + '" gallery="' + gallery.value + '"]';
		}
	}

	function announceCopied() {
		if (status && window.HLPBShortcodeBuilder) {
			status.textContent = window.HLPBShortcodeBuilder.copied;
		}
	}

	if (landing) {
		landing.addEventListener('change', update);
	}
	if (gallery) {
		gallery.addEventListener('change', update);
	}
	if (button) {
		button.addEventListener('click', function () {
			if (!output) {
				return;
			}

			output.focus();
			output.select();
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(output.value).then(announceCopied);
			} else {
				document.execCommand('copy');
				announceCopied();
			}
		});
	}

	update();
}());
