(function () {
	'use strict';
	var search = document.getElementById('hlpb-guide-search');
	var sections = Array.prototype.slice.call(document.querySelectorAll('[data-guide-section]'));
	var navLinks = Array.prototype.slice.call(document.querySelectorAll('.hlpb-guide-nav > a[href^="#"]'));
	var empty = document.querySelector('.hlpb-guide-search-empty');
	if (!search || !sections.length) return;

	function applySearch() {
		var query = search.value.trim().toLocaleLowerCase();
		var visibleCount = 0;
		sections.forEach(function (section) {
			var matches = !query || section.textContent.toLocaleLowerCase().indexOf(query) !== -1;
			section.hidden = !matches;
			if (matches) visibleCount += 1;
		});
		navLinks.forEach(function (link) {
			var section = document.querySelector(link.getAttribute('href'));
			link.hidden = !!(query && section && section.hidden);
		});
		if (empty) empty.hidden = visibleCount > 0;
	}

	search.addEventListener('input', applySearch);
	search.addEventListener('keydown', function (event) {
		if (event.key !== 'Enter') return;
		var firstVisible = sections.filter(function (section) { return !section.hidden; })[0];
		if (firstVisible) firstVisible.scrollIntoView({ behavior: 'smooth', block: 'start' });
	});

	if ('IntersectionObserver' in window) {
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				navLinks.forEach(function (link) {
					link.classList.toggle('is-active', link.getAttribute('href') === '#' + entry.target.id);
				});
			});
		}, { rootMargin: '-12% 0px -72% 0px' });
		sections.forEach(function (section) { observer.observe(section); });
	}
}());
