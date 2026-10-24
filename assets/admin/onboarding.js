(function () {
	'use strict';

	var overlay = document.querySelector('.hlpb-onboarding-overlay');
	if (!overlay || !window.HLPBOnboarding) return;

	var importButton = overlay.querySelector('[data-hlpb-import]');
	var progress = overlay.querySelector('[data-hlpb-progress]');
	var status = overlay.querySelector('[data-hlpb-status]');
	var resultBox = overlay.querySelector('[data-hlpb-result]');
	var resultTitle = overlay.querySelector('[data-hlpb-result-title]');
	var reportList = overlay.querySelector('[data-hlpb-report]');
	var landingLink = overlay.querySelector('[data-hlpb-landing]');
	var progressTimers = [];

	function clearProgressTimers() {
		progressTimers.forEach(function (timer) {
			window.clearTimeout(timer);
		});
		progressTimers = [];
	}

	function scheduleProgressMessages() {
		clearProgressTimers();
		[
			[15000, window.HLPBOnboarding.importing],
			[60000, window.HLPBOnboarding.finishing],
			[150000, window.HLPBOnboarding.longWait]
		].forEach(function (step) {
			progressTimers.push(window.setTimeout(function () {
				if (!progress.hidden) status.textContent = step[1] || window.HLPBOnboarding.busy;
			}, step[0]));
		});
	}

	function showResult(payload, isError) {
		clearProgressTimers();
		progress.hidden = true;
		resultBox.hidden = false;
		resultBox.classList.toggle('hlpb-onboarding-result--error', Boolean(isError));
		resultTitle.textContent = payload.message || window.HLPBOnboarding.error;
		reportList.replaceChildren();
		(payload.report || []).forEach(function (line) {
			var item = document.createElement('li');
			item.textContent = String(line);
			reportList.appendChild(item);
		});

		if (payload.landing_url) {
			landingLink.href = payload.landing_url;
			landingLink.textContent = window.HLPBOnboarding.openLanding;
			landingLink.hidden = false;
		} else {
			landingLink.hidden = true;
		}
		if (importButton) importButton.hidden = !isError;
	}

	if (!importButton) return;
	importButton.addEventListener('click', function () {
		if (importButton.disabled) return;
		importButton.disabled = true;
		progress.hidden = false;
		resultBox.hidden = true;
		status.textContent = window.HLPBOnboarding.busy;
		scheduleProgressMessages();

		var body = new FormData();
		body.append('action', 'hlpb_activation_import');
		body.append('nonce', window.HLPBOnboarding.nonce);

		fetch(window.HLPBOnboarding.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (response) {
			return response.text().then(function (text) {
				var data;
				try {
					data = JSON.parse(text);
				} catch (parseError) {
					throw {
						message: String(window.HLPBOnboarding.invalidResponse || window.HLPBOnboarding.error).replace('%d', response.status),
						report: []
					};
				}
				if (!response.ok || !data || !data.success) {
					var errorData = data && data.data ? data.data : {};
					throw { message: errorData.message || window.HLPBOnboarding.error, report: errorData.report || [] };
				}
				return data.data || {};
			});
		}).then(function (payload) {
			showResult(payload, 'partial' === payload.status);
			importButton.disabled = false;
		}).catch(function (error) {
			showResult({ message: error && error.message ? error.message : window.HLPBOnboarding.error, report: error && error.report ? error.report : [] }, true);
			importButton.disabled = false;
		});
	});
}());
