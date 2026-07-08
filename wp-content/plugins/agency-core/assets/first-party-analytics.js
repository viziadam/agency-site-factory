(function () {
	'use strict';
	var config = window.agencyFirstPartyAnalytics || {};
	var started = false;
	function consentGranted() {
		return document.cookie.split('; ').some(function (item) { return item === 'agency_analytics_consent=granted'; });
	}
	function start() {
		if (started || !config.endpoint || !consentGranted()) return;
		started = true;
		var session = sessionStorage.getItem('agency_session_id');
		if (!session) {
			session = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : Date.now() + '-' + Math.random();
			sessionStorage.setItem('agency_session_id', session);
		}
		function send(type, name) {
			var body = JSON.stringify({ type: type, name: name, path: location.pathname, title: document.title, session: session });
			if (navigator.sendBeacon) {
				navigator.sendBeacon(config.endpoint, new Blob([body], { type: 'application/json' }));
			} else {
				fetch(config.endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true });
			}
		}
		send('pageview', 'page_view');
		document.addEventListener('click', function (event) {
			var target = event.target.closest('[data-agency-track],a,button');
			if (!target) return;
			var explicit = target.dataset.agencyTrack;
			var href = target.getAttribute('href') || '';
			var text = (target.textContent || '').toLowerCase();
			if (explicit) send('interaction', explicit);
			else if (/idopont|foglal|booking/.test(href + ' ' + text)) send('interaction', 'booking_click');
		});
	}
	start();
	window.addEventListener('agency:analytics-consent', function (event) {
		if (event.detail && event.detail.granted) start();
	});
}());
