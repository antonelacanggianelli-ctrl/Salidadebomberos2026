<?php
declare(strict_types=1);

/**
 * Servicio web publico de solo lectura.
 *   GET api.php?recurso=alertas    -> alertas vigentes + resumen del estado
 *   GET api.php?recurso=contactos  -> telefonos y punto de encuentro
 */

require_once __DIR__ . '/app/repository.php';
require_once __DIR__ . '/app/helpers.php';

send_security_headers();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Metodo no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$resource = isset($_GET['recurso']) && is_string($_GET['recurso']) ? $_GET['recurso'] : 'alertas';

try {
    $payload = match ($resource) {
        'alertas' => (static function (): array {
            $alerts = alerts_public();

            return [
                'generado'  => gmdate('c'),
                'resumen'   => alerts_summary($alerts),
                'alertas'   => array_map(static fn (array $a) => [
                    'id'               => (int) $a['id'],
                    'titulo'           => $a['title'],
                    'tipo'             => $a['type'],
                    'severidad'        => $a['severity'],
                    'estado'           => $a['status'],
                    'ubicacion'        => $a['location'],
                    'punto_encuentro'  => $a['meeting_point'],
                    'descripcion'      => $a['description'],
                    'indicaciones'     => $a['instructions'],
                    'reporta'          => $a['author_name'],
                    'creada'           => str_replace(' ', 'T', $a['created_at']) . 'Z',
                    'actualizada'      => str_replace(' ', 'T', $a['updated_at']) . 'Z',
                ], $alerts),
            ];
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
        echo json_encode(['error' => 'Recurso desconocido', 'disponibles' => ['alertas', 'contactos']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode(['error' => 'No fue posible consultar las alertas'], JSON_UNESCAPED_UNICODE);
}
