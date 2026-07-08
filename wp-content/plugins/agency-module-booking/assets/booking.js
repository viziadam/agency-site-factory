(function () {
	'use strict';
	var copy = window.agencyBookingI18n || {};

	function init(form) {
		var state = { step: 1, service: null, date: '', slot: '', month: new Date() };
		state.month.setDate(1);
		var panels = form.querySelectorAll('[data-step]');
		var steps = form.querySelectorAll('[data-go-step]');
		var calendarGrid = form.querySelector('.agency-booking-calendar-grid');
		var calendarTitle = form.querySelector('[data-calendar-title]');
		var slots = form.querySelector('.agency-booking-slot-grid');
		var slotMessage = form.querySelector('.agency-booking-slots-message');

		function summary(key, value) {
			var node = form.querySelector('[data-summary-' + key + ']');
			if (node) node.textContent = value || '—';
		}
		function go(step) {
			if (step > 1 && !state.service) return;
			if (step > 2 && !state.date) return;
			if (step > 3 && !state.slot) return;
			state.step = step;
			panels.forEach(function (panel) { panel.classList.toggle('is-active', Number(panel.dataset.step) === step); });
			steps.forEach(function (button) {
				var number = Number(button.dataset.goStep);
				button.classList.toggle('is-active', number === step);
				button.classList.toggle('is-complete', number < step);
				button.disabled = number > step || (number === 3 && !state.date) || (number === 4 && !state.slot);
				button.setAttribute('aria-current', number === step ? 'step' : 'false');
			});
			form.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
		function request(url, params) {
			var endpoint = new URL(url, window.location.href);
			Object.keys(params).forEach(function (key) { endpoint.searchParams.set(key, params[key]); });
			return fetch(endpoint.toString(), { credentials: 'same-origin' }).then(function (response) {
				if (!response.ok) throw new Error('request');
				return response.json();
			});
		}
		function renderCalendar() {
			calendarGrid.innerHTML = '<p class="agency-booking-loading">' + (copy.loading || 'Loading…') + '</p>';
			var month = state.month.getFullYear() + '-' + String(state.month.getMonth() + 1).padStart(2, '0');
			request(form.dataset.calendarUrl, { month: month, service_id: state.service.id }).then(function (payload) {
				calendarTitle.textContent = payload.label;
				calendarGrid.replaceChildren();
				for (var blank = 0; blank < payload.first_weekday; blank++) {
					var spacer = document.createElement('span');
					spacer.className = 'agency-booking-calendar-empty';
					calendarGrid.appendChild(spacer);
				}
				payload.days.forEach(function (day) {
					var button = document.createElement('button');
					button.type = 'button';
					button.disabled = day.disabled;
					button.dataset.date = day.date;
					button.innerHTML = '<strong>' + day.day + '</strong><small>' + (day.available ? day.available + ' ' + (copy.available || 'available') : (copy.closed || 'closed')) + '</small>';
					button.className = day.date === state.date ? 'is-selected' : '';
					button.addEventListener('click', function () {
						state.date = day.date;
						state.slot = '';
						form.elements.booking_date.value = day.date;
						form.elements.start_at.value = '';
						summary('date', new Intl.DateTimeFormat(document.documentElement.lang || undefined, { dateStyle: 'long' }).format(new Date(day.date + 'T12:00:00')));
						summary('time', '—');
						loadSlots();
						go(3);
					});
					calendarGrid.appendChild(button);
				});
			}).catch(function () { calendarGrid.innerHTML = '<p class="agency-booking-error">' + (copy.error || 'Calendar could not be loaded.') + '</p>'; });
		}
		function loadSlots() {
			slots.replaceChildren();
			slotMessage.hidden = false;
			slotMessage.textContent = copy.loading || 'Loading…';
			request(form.dataset.slotsUrl, { date: state.date, service_id: state.service.id }).then(function (payload) {
				slotMessage.hidden = Boolean(payload.slots && payload.slots.length);
				slotMessage.textContent = copy.empty || 'No available times.';
				(payload.slots || []).forEach(function (slot) {
					var button = document.createElement('button');
					button.type = 'button';
					button.className = 'agency-booking-slot';
					button.textContent = slot.label;
					button.setAttribute('aria-pressed', 'false');
					button.addEventListener('click', function () {
						slots.querySelectorAll('button').forEach(function (item) { item.classList.remove('is-selected'); item.setAttribute('aria-pressed', 'false'); });
						button.classList.add('is-selected');
						button.setAttribute('aria-pressed', 'true');
						state.slot = slot.value;
						form.elements.start_at.value = slot.value;
						summary('time', slot.label);
						setTimeout(function () { go(4); }, 180);
					});
					slots.appendChild(button);
				});
			}).catch(function () { slotMessage.textContent = copy.error || 'Available times could not be loaded.'; });
		}
		form.querySelectorAll('.agency-booking-service').forEach(function (button) {
			button.setAttribute('aria-pressed', 'false');
			button.addEventListener('click', function () {
				form.querySelectorAll('.agency-booking-service').forEach(function (item) { item.classList.remove('is-selected'); item.setAttribute('aria-pressed', 'false'); });
				button.classList.add('is-selected');
				button.setAttribute('aria-pressed', 'true');
				state.service = { id: button.dataset.serviceId, title: button.dataset.serviceTitle, duration: button.dataset.duration, price: button.dataset.price, currency: button.dataset.currency };
				form.elements.service_id.value = state.service.id;
				summary('service', state.service.title);
				summary('duration', state.service.duration + ' min');
				summary('price', Number(state.service.price).toLocaleString(document.documentElement.lang || undefined) + ' ' + state.service.currency);
				renderCalendar();
				go(2);
			});
		});
		form.querySelectorAll('[data-back-step]').forEach(function (button) { button.addEventListener('click', function () { go(Number(button.dataset.backStep)); }); });
		steps.forEach(function (button) { button.addEventListener('click', function () { go(Number(button.dataset.goStep)); }); });
		form.querySelector('[data-calendar-prev]').addEventListener('click', function () { state.month.setMonth(state.month.getMonth() - 1); renderCalendar(); });
		form.querySelector('[data-calendar-next]').addEventListener('click', function () { state.month.setMonth(state.month.getMonth() + 1); renderCalendar(); });
		var weekdays = form.querySelector('.agency-booking-calendar-weekdays');
		(copy.weekdays || ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']).forEach(function (day) { var span = document.createElement('span'); span.textContent = day; weekdays.appendChild(span); });
		form.addEventListener('submit', function () { var submit = form.querySelector('.agency-booking-submit'); submit.disabled = true; submit.textContent = copy.sending || 'Sending…'; });
		go(1);
	}
	document.querySelectorAll('.agency-booking-wizard').forEach(init);
}());
