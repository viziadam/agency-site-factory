(function () {
	'use strict';
	var config = window.agencyAnalytics || {};
	if (!config.measurementId || (config.respectDnt && (navigator.doNotTrack === '1' || window.doNotTrack === '1'))) return;
	function hasConsent() {
		return document.cookie.split('; ').some(function (item) { return item === config.consentCookie + '=granted'; });
	}
	function load() {
		if (window.agencyAnalyticsLoaded) return;
		window.agencyAnalyticsLoaded = true;
		window.dataLayer = window.dataLayer || [];
		window.gtag = function () { window.dataLayer.push(arguments); };
		window.gtag('js', new Date());
		window.gtag('config', config.measurementId, { anonymize_ip: true });
		var script = document.createElement('script');
		script.async = true;
		script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(config.measurementId);
		document.head.appendChild(script);
	}
	if (!config.consentRequired || hasConsent()) load();
	window.addEventListener('agency:analytics-consent', function (event) {
		if (!event.detail || !event.detail.granted) return;
		document.cookie = config.consentCookie + '=granted; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
		load();
	});
}());
