<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Notificaciones push (Firebase Cloud Messaging).
 *
 * Estado de la integracion: el registro de dispositivos y el disparo de
 * envio estan implementados de punta a punta. Lo unico que falta para que
 * realmente salga la notificacion es cargar las credenciales reales del
 * proyecto de Firebase en app/Legacy/app/config.php (FCM_PROJECT_ID,
 * FCM_SERVICE_ACCOUNT_KEY, FCM_VAPID_PUBLIC_KEY, FCM_WEB_API_KEY, etc.).
 * Mientras esas constantes esten vacias, fcm_is_configured() devuelve false
 * y el sistema sigue funcionando normalmente: solo no se envia el push
 * (no rompe la creacion ni el cierre de alertas).
 */

/** True si hay credenciales de Firebase cargadas. */
function fcm_is_configured(): bool
{
    return FCM_PROJECT_ID !== '' && is_file(FCM_SERVICE_ACCOUNT_KEY);
}

/**
 * True si el navegador tiene lo necesario para pedir permiso de notificacion
 * y registrar su token (config web + clave VAPID). No requiere la cuenta de
 * servicio: eso solo hace falta del lado del servidor para poder enviar.
 */
function fcm_client_ready(): bool
{
    return FCM_PROJECT_ID !== '' && FCM_WEB_API_KEY !== '' && FCM_APP_ID !== ''
        && FCM_MESSAGING_SENDER_ID !== '' && FCM_VAPID_PUBLIC_KEY !== '';
}

/** Configuracion publica de Firebase Web, lista para pasar al JS del portal. */
function fcm_client_config(): array
{
    return [
        'apiKey'            => FCM_WEB_API_KEY,
        'projectId'         => FCM_PROJECT_ID,
        'messagingSenderId' => FCM_MESSAGING_SENDER_ID,
        'appId'             => FCM_APP_ID,
        'vapidKey'          => FCM_VAPID_PUBLIC_KEY,
    ];
}

/* -------------------------------------------------------- Device tokens -- */

/** Registra o refresca un token de dispositivo suscripto a notificaciones. */
function device_token_register(string $token, string $zone = ''): void
{
    $now = now_utc();

    if (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql = 'INSERT INTO device_tokens (token, zone, active, created_at, last_seen_at)
                VALUES (?, ?, 1, ?, ?)
                ON DUPLICATE KEY UPDATE zone = VALUES(zone), active = 1, last_seen_at = VALUES(last_seen_at)';
    } else {
        $sql = 'INSERT INTO device_tokens (token, zone, active, created_at, last_seen_at)
                VALUES (?, ?, 1, ?, ?)
                ON CONFLICT(token) DO UPDATE SET zone = excluded.zone, active = 1, last_seen_at = excluded.last_seen_at';
    }

    db()->prepare($sql)->execute([$token, $zone, $now, $now]);
}

function device_token_unregister(string $token): void
{
    db()->prepare('UPDATE device_tokens SET active = 0 WHERE token = ?')->execute([$token]);
}

/** Tokens activos, opcionalmente filtrados por zona (vacio = todas las zonas). */
function device_tokens_active(string $zone = ''): array
{
    if ($zone !== '') {
        $stmt = db()->prepare("SELECT token FROM device_tokens WHERE active = 1 AND (zone = ? OR zone = '')");
        $stmt->execute([$zone]);
    } else {
        $stmt = db()->query('SELECT token FROM device_tokens WHERE active = 1');
    }

    return array_column($stmt->fetchAll(), 'token');
}

function device_tokens_count_active(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM device_tokens WHERE active = 1')->fetchColumn();
}

/* -------------------------------------------------------------- Disparo -- */

/** Notifica a los dispositivos suscriptos que hay una alerta nueva. */
function fcm_notify_new_alert(int $alertId): void
{
    $alert = alert_find($alertId);
    if ($alert === null || (int) $alert['published'] !== 1) {
        return;
    }

    fcm_send_to_zone($alert['zone'] ?? '', [
        'title' => 'Nueva alerta: ' . $alert['title'],
        'body'  => mb_strimwidth((string) $alert['description'], 0, 120, '...'),
        'tag'   => 'alerta-' . $alertId,
    ]);
}

/** Notifica que una alerta paso a estado "cerrada". */
function fcm_notify_alert_closed(int $alertId): void
{
    $alert = alert_find($alertId);
    if ($alert === null || (int) $alert['published'] !== 1) {
        return;
    }

    fcm_send_to_zone($alert['zone'] ?? '', [
        'title' => 'Alerta cerrada: ' . $alert['title'],
        'body'  => 'La situacion fue controlada. Revisa el historial en la portada.',
        'tag'   => 'alerta-' . $alertId,
    ]);
}

/**
 * Envia una notificacion a los tokens activos de una zona (o a todos si la
 * zona viene vacia) usando la API HTTP v1 de Firebase Cloud Messaging.
 * Si no hay credenciales cargadas, registra la intencion en la bitacora y
 * no intenta la llamada de red (para no romper el flujo en desarrollo).
 *
 * @param array{title:string, body:string, tag?:string} $notification
 */
function fcm_send_to_zone(string $zone, array $notification): void
{
    $tokens = device_tokens_active($zone);
    if ($tokens === []) {
        error_log('PUSH: no hay dispositivos registrados (tokens activos = 0)');
        return;
    }

    if (!fcm_is_configured()) {
        error_log('PUSH: Firebase no configurado. project=' . (FCM_PROJECT_ID !== '' ? 'ok' : 'VACIO')
            . ' archivo=' . FCM_SERVICE_ACCOUNT_KEY . ' existe=' . (is_file(FCM_SERVICE_ACCOUNT_KEY) ? 'si' : 'NO'));
        audit('push_omitido', 'Firebase no configurado. Se hubiera notificado a ' . count($tokens) . ' dispositivo(s): ' . $notification['title']);
        return;
    }

    try {
        $accessToken = fcm_access_token();
    } catch (Throwable $e) {
        error_log('PUSH ERROR token: ' . $e->getMessage());
        audit('push_error', 'No se pudo obtener token de acceso de Firebase: ' . $e->getMessage());
        return;
    }

    $enviados = 0;
    foreach ($tokens as $token) {
        $ok = fcm_send_single($accessToken, $token, $notification);
        if ($ok) {
            $enviados++;
        } else {
            // Token invalido o expirado: se da de baja para no reintentar en vano.
            device_token_unregister($token);
        }
    }

    error_log('PUSH: enviados ' . $enviados . '/' . count($tokens));
    audit('push_enviado', $notification['title'] . ' -> ' . $enviados . '/' . count($tokens) . ' dispositivo(s).');
}

/** Un unico mensaje via FCM HTTP v1. Devuelve true si Firebase lo acepto. */
function fcm_send_single(string $accessToken, string $token, array $notification): bool
{
    $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode(FCM_PROJECT_ID) . '/messages:send';
    $body = json_encode([
        'message' => [
            'token'        => $token,
            'notification' => [
                'title' => $notification['title'],
                'body'  => $notification['body'],
            ],
            'webpush' => [
                'fcm_options' => ['link' => '/'],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=utf-8',
        ],
        CURLOPT_POSTFIELDS => $body,
    ]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status < 200 || $status >= 300) {
        error_log('PUSH ERROR FCM http=' . $status . ' curl=' . curl_error($ch) . ' resp=' . substr((string) $resp, 0, 300));
    }
    curl_close($ch);

    return $status >= 200 && $status < 300;
}

/**
 * Obtiene un access token OAuth2 para la API de Firebase a partir de la
 * cuenta de servicio (JSON descargado desde la consola de Firebase).
 * Requiere que FCM_SERVICE_ACCOUNT_KEY apunte a ese archivo.
 */
function fcm_access_token(): string
{
    $raw = file_get_contents(FCM_SERVICE_ACCOUNT_KEY);
    if ($raw === false) {
        throw new RuntimeException('No se pudo leer la cuenta de servicio de Firebase.');
    }
    $account = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

    $now = time();
    $header  = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims  = [
        'iss'   => $account['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ];

    $segments = [
        rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '='),
        rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '='),
    ];
    $signingInput = implode('.', $segments);

    openssl_sign($signingInput, $signature, $account['private_key'], 'sha256WithRSAEncryption');
    $segments[] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    $jwt = implode('.', $segments);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode((string) $response, true);
    if (!isset($data['access_token'])) {
                throw new RuntimeException('Firebase no devolvio un access token valido. Respuesta: ' . substr((string) $response, 0, 300));
    }

    return $data['access_token'];
}
