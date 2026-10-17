<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Escapa texto para imprimirlo en HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Marca de tiempo actual en UTC (formato ISO ordenable). */
function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** Convierte una marca UTC guardada en base a la zona horaria del cuartel. */
function local_dt(?string $utc): ?DateTimeImmutable
{
    if ($utc === null || $utc === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(APP_TIMEZONE));
    } catch (Exception) {
        return null;
    }
}

/** Fecha legible: 08 sep 2026, 14:35 */
function fmt_dt(?string $utc): string
{
    $dt = local_dt($utc);
    if (!$dt instanceof DateTimeImmutable) {
        return '-';
    }
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    return $dt->format('d') . ' ' . $meses[(int) $dt->format('n') - 1] . ' ' . $dt->format('Y, H:i');
}

/** Antiguedad en lenguaje natural: "hace 12 min". */
function fmt_ago(?string $utc): string
{
    $dt = local_dt($utc);
    if (!$dt instanceof DateTimeImmutable) {
        return '-';
    }
    $seconds = time() - $dt->getTimestamp();
    if ($seconds < 60) {
        return 'hace instantes';
    }
    if ($seconds < 3600) {
        return 'hace ' . intdiv($seconds, 60) . ' min';
    }
    if ($seconds < 86400) {
        $h = intdiv($seconds, 3600);

        return 'hace ' . $h . ($h === 1 ? ' hora' : ' horas');
    }
    $d = intdiv($seconds, 86400);

    return 'hace ' . $d . ($d === 1 ? ' dia' : ' dias');
}

/** Redirige y corta la ejecucion. */
function sdb_redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Recorta y normaliza un campo de formulario. */
function post_str(string $key, int $maxLength = 255): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '');

    return mb_substr($value, 0, $maxLength);
}

/** Cabeceras de seguridad basicas para todas las paginas. */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

/** Direccion IP del cliente (solo informativa, para la bitacora). */
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    return is_string($ip) ? mb_substr($ip, 0, 45) : '0.0.0.0';
}

/** Clase CSS asociada a la severidad de una alerta. */
function severity_class(string $severity): string
{
    return match ($severity) {
        'critica' => 'sev-critica',
        'alta'    => 'sev-alta',
        'media'   => 'sev-media',
        default   => 'sev-baja',
    };
}

/** Clase CSS asociada al estado de una alerta. */
function status_class(string $status): string
{
    return match ($status) {
        'activa'     => 'st-activa',
        'controlada' => 'st-controlada',
        default      => 'st-cerrada',
    };
}

/** Deja solo digitos, conservando un '+' inicial si lo escribieron. */
function normalize_phone(string $raw): string
{
    $raw    = trim($raw);
    $plus   = str_starts_with($raw, '+') ? '+' : '';
    $digits = preg_replace('/\D+/', '', $raw) ?? '';

    return $plus . $digits;
}

/** Valida un celular ya normalizado. Devuelve null si es valido. */
function phone_problem(string $phone): ?string
{
    $digits = ltrim($phone, '+');
    if ($digits === '' || !ctype_digit($digits)) {
        return 'Ingresa un numero de celular valido.';
    }
    if (mb_strlen($digits) < 8 || mb_strlen($digits) > 15) {
        return 'El numero debe tener entre 8 y 15 digitos, con codigo de area.';
    }

    return null;
}
