(function () {
	'use strict';
	var banner = document.querySelector('[data-agency-cookie-banner]');
	if (!banner) return;
	var cookie = document.cookie.split('; ').find(function (item) { return item.indexOf('agency_analytics_consent=') === 0; });
	if (!cookie) banner.hidden = false;
	banner.querySelectorAll('[data-agency-consent]').forEach(function (button) {
		button.addEventListener('click', function () {
			var value = button.dataset.agencyConsent;
			document.cookie = 'agency_analytics_consent=' + value + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
			banner.hidden = true;
			window.dispatchEvent(new CustomEvent('agency:analytics-consent', { detail: { granted: value === 'granted' } }));
		});
	});
}());
