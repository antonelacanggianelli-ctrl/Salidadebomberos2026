<?php
declare(strict_types=1);

/**
 * Servicio web publico.
 *   GET  api.php?recurso=alertas       -> alertas vigentes + resumen del estado (admite filtros: tipo, zona, estado)
 *   GET  api.php?recurso=alerta&id=N   -> detalle de una alerta publicada
 *   GET  api.php?recurso=contactos     -> telefonos y punto de encuentro
 *   POST api.php?recurso=push-token    -> registra el token de notificaciones push del navegador (requiere csrf)
 *   POST api.php?recurso=push-token-baja -> da de baja un token (requiere csrf)
 */

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/repository.php';
require_once __DIR__ . '/app/helpers.php';

send_security_headers();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$resource = isset($_GET['recurso']) && is_string($_GET['recurso']) ? $_GET['recurso'] : 'alertas';
$method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$writableResources = ['push-token', 'push-token-baja'];

if ($method === 'POST' && !in_array($resource, $writableResources, true)) {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Metodo no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($method !== 'GET' && $method !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['error' => 'Metodo no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (in_array($resource, $writableResources, true) && $method !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Este recurso solo admite POST'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($method === 'POST') {
        session_boot();
        csrf_verify();

        $token = post_str('token', 250);
        if ($token === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Falta el token de dispositivo.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($resource === 'push-token') {
            $zone = post_str('zona', 60);
            if ($zone !== '' && !in_array($zone, ALERT_ZONES, true)) {
                $zone = '';
            }
            device_token_register($token, $zone);
            echo json_encode(['ok' => true, 'mensaje' => 'Suscripcion a notificaciones activada.'], JSON_UNESCAPED_UNICODE);
        } else {
            device_token_unregister($token);
            echo json_encode(['ok' => true, 'mensaje' => 'Suscripcion a notificaciones dada de baja.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    $payload = match ($resource) {
        'alertas' => (static function (): array {
            $filters = ['published_only' => true];
            $tipo   = get_str('tipo', 60);
            $zona   = get_str('zona', 60);
            $estado = get_str('estado', 20);
            if ($tipo !== '' && in_array($tipo, ALERT_TYPES, true)) {
                $filters['type'] = $tipo;
            }
            if ($zona !== '' && in_array($zona, ALERT_ZONES, true)) {
                $filters['zone'] = $zona;
            }
            if ($estado !== '' && in_array($estado, ALERT_STATUSES, true)) {
                $filters['status'] = $estado;
            }
            $hasFilters = $filters !== ['published_only' => true];

            $alerts = $hasFilters ? alerts_list($filters, 100) : alerts_public();
            if ($hasFilters && $estado === '') {
                $alerts = array_values(array_filter($alerts, static fn (array $a) => $a['status'] !== 'cerrada'));
            }

            return [
                'generado'  => gmdate('c'),
                'filtros'   => ['tipo' => $tipo ?: null, 'zona' => $zona ?: null, 'estado' => $estado ?: null],
                'resumen'   => alerts_summary(alerts_public()),
                'alertas'   => array_map('api_format_alert', $alerts),
            ];
        })(),
        'alerta' => (static function (): ?array {
            $id    = (int) ($_GET['id'] ?? 0);
            $alert = $id > 0 ? alert_find($id) : null;
            if ($alert === null || (int) $alert['published'] !== 1) {
                return null;
            }

            return ['generado' => gmdate('c'), 'alerta' => api_format_alert($alert)];
        })(),
        'contactos' => [
            'generado'  => gmdate('c'),
            'contactos' => array_map(static fn (array $c) => [
                'etiqueta' => $c['label'],
                'valor'    => $c['number'],
                'tono'     => $c['tone'],
            ], contacts_all()),
        ],
        default => null,
    };

    if ($payload === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Recurso desconocido o alerta inexistente', 'disponibles' => ['alertas', 'alerta', 'contactos']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode(['error' => 'No fue posible atender la solicitud'], JSON_UNESCAPED_UNICODE);
}

/** Da forma consistente a una alerta para cualquier endpoint de la API. */
function api_format_alert(array $a): array
{
    return [
        'id'               => (int) $a['id'],
        'titulo'           => $a['title'],
        'tipo'             => $a['type'],
        'severidad'        => $a['severity'],
        'estado'           => $a['status'],
        'zona'             => $a['zone'] ?? '',
        'ubicacion'        => $a['location'],
        'punto_encuentro'  => $a['meeting_point'],
        'descripcion'      => $a['description'],
        'indicaciones'     => $a['instructions'],
        'reporta'          => $a['author_name'],
        'creada'           => str_replace(' ', 'T', $a['created_at']) . 'Z',
        'actualizada'      => str_replace(' ', 'T', $a['updated_at']) . 'Z',
    ];
}

