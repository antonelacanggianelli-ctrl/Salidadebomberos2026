/**
 * Notificaciones push del portal (Semana 6).
 * Requiere que window.__FCM_CONFIG__ ya este definido (lo imprime index.php
 * solo cuando hay credenciales de Firebase cargadas).
 */
(function () {
	'use strict';

	var config = window.__FCM_CONFIG__;
	var btn = document.getElementById('pushEnableBtn');
	var status = document.getElementById('pushStatus');
	var zoneSelect = document.getElementById('push_zone');
	if (!config || !btn) {
		return;
	}

	function setStatus(text, isError) {
		if (!status) { return; }
		status.textContent = text;
		status.style.color = isError ? '#b23a1e' : '#1e7a3a';
	}

	function csrfToken() {
		var meta = document.querySelector('meta[name="csrf-token"]');
		return meta ? meta.content : '';
	}

	function postToken(accion, token) {
		var body = new URLSearchParams();
		body.set('csrf', csrfToken());
		body.set('token', token);
		if (accion === 'push-token') {
			body.set('zona', zoneSelect ? zoneSelect.value : '');
		}
		return fetch('api.php?recurso=' + accion, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		}).then(function (r) { return r.json(); });
	}

	function loadScript(src) {
		return new Promise(function (resolve, reject) {
			var s = document.createElement('script');
			s.src = src;
			s.onload = resolve;
			s.onerror = reject;
			document.head.appendChild(s);
		});
	}

	function loadFirebaseSdk() {
		if (window.firebase && window.firebase.messaging) {
			return Promise.resolve();
		}
		return loadScript('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js')
			.then(function () {
				return loadScript('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');
			});
	}

	function activar() {
		if (!('Notification' in window) || !('serviceWorker' in navigator)) {
			setStatus('Este navegador no admite notificaciones push.', true);
			return;
		}

		btn.disabled = true;
		setStatus('Pidiendo permiso...');

		Notification.requestPermission()
			.then(function (permiso) {
				if (permiso !== 'granted') {
					throw new Error('No diste permiso de notificaciones.');
				}
				setStatus('Preparando...');
				return loadFirebaseSdk();
			})
			.then(function () {
				var swUrl = 'firebase-messaging-sw.js?' + new URLSearchParams(config).toString();
				return navigator.serviceWorker.register(swUrl);
			})
			.then(function (registration) {
				if (!window.firebase.apps || !window.firebase.apps.length) {
					window.firebase.initializeApp(config);
				}
				var messaging = window.firebase.messaging();

				return messaging.getToken({
					vapidKey: config.vapidKey,
					serviceWorkerRegistration: registration,
				});
			})
			.then(function (token) {
				if (!token) {
					throw new Error('No se pudo obtener el token del dispositivo.');
				}
				return postToken('push-token', token);
			})
			.then(function (respuesta) {
				if (!respuesta.ok) {
					throw new Error(respuesta.error || 'El servidor rechazo la suscripcion.');
				}
				setStatus('Notificaciones activadas. Ya vas a recibir los avisos en este dispositivo.');
				btn.textContent = 'Notificaciones activadas';
			})
			.catch(function (err) {
				setStatus(err.message || 'No se pudo activar.', true);
				btn.disabled = false;
			});
	}

	btn.addEventListener('click', activar);
})();
