<?php
declare(strict_types=1);

/**
 * Router para el servidor embebido de PHP (no se usa en Apache/XAMPP).
 *   php -S localhost:8000 dev-server.php
 * Bloquea las carpetas internas, que en Apache ya protege el .htaccess.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (preg_match('#^/(app|data)(/|$)#i', $path)) {
    http_response_code(403);
    exit('403 - Acceso denegado.');
}

// El resto lo resuelve el servidor embebido con su comportamiento normal.
return false;
