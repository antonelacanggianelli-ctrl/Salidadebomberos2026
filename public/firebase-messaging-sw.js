/**
 * Service worker de notificaciones push (Semana 6).
 * Recibe las credenciales por la URL con la que se lo registra en push.js,
 * asi este archivo no cambia nunca aunque cambien las credenciales.
 */
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');

var params = new URLSearchParams(self.location.search);
var config = {
	apiKey: params.get('apiKey'),
	projectId: params.get('projectId'),
	messagingSenderId: params.get('messagingSenderId'),
	appId: params.get('appId'),
};

firebase.initializeApp(config);
var messaging = firebase.messaging();

messaging.onBackgroundMessage(function (payload) {
	var titulo = (payload.notification && payload.notification.title) || 'SalidadeBomberos!';
	var cuerpo = (payload.notification && payload.notification.body) || '';

	self.registration.showNotification(titulo, {
		body: cuerpo,
		tag: (payload.notification && payload.notification.tag) || 'sdb-alerta',
	});
});
