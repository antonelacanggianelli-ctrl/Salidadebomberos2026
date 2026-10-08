<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/** Inicia la sesion con cookies endurecidas y control de inactividad. */
function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Cierre por inactividad.
    if (isset($_SESSION['user_id'], $_SESSION['last_seen'])) {
        if (time() - (int) $_SESSION['last_seen'] > SESSION_IDLE_TIMEOUT) {
            session_destroy_all();
            session_start();
            $_SESSION['flash'] = ['warn', 'Tu sesion se cerro por inactividad. Ingresa nuevamente.'];
        }
    }
    $_SESSION['last_seen'] = time();
}

/** Destruye por completo la sesion actual. */
function session_destroy_all(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

/* ---------------------------------------------------------------- CSRF ---- */

function sdb_csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf'];
}

function sdb_csrf_field(): string
{
	return '<input type="hidden" name="csrf" value="' . e(sdb_csrf_token()) . '">';
}

/** Verifica el token en toda peticion POST; corta la ejecucion si no coincide. */
function csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? '';
	if (!is_string($sent) || !hash_equals(sdb_csrf_token(), $sent)) {
        http_response_code(403);
        exit('Token de seguridad invalido o vencido. Vuelve atras y reintenta.');
    }
}

/* -------------------------------------------------------------- Mensajes -- */

function flash(string $type, string $message): void
{
    session_boot();
    $_SESSION['flash'] = [$type, $message];
}

/** Devuelve y limpia el mensaje pendiente. */
function flash_pull(): ?array
{
    session_boot();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

/** Guarda lo escrito en un formulario para repintarlo tras un error. */
function stash_old(array $data): void
{
    session_boot();
    $_SESSION['old'] = $data;
}

/** Devuelve y limpia los datos guardados por stash_old(). */
function pull_old(): ?array
{
    session_boot();
    $old = $_SESSION['old'] ?? null;
    unset($_SESSION['old']);

    return is_array($old) ? $old : null;
}

/* --------------------------------------------------------------- Sesion --- */

/** Usuario autenticado (fila completa) o null. */
function current_user(): ?array
{
    static $cache = null;
    static $cachedId = null;

    session_boot();
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }
    if ($cache !== null && $cachedId === $id) {
        return $cache;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user === false) {
        // Cuenta eliminada o desactivada mientras la sesion seguia abierta.
        session_destroy_all();

        return null;
    }

    $cachedId = $id;
    $cache = $user;

    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

/** Exige sesion activa con uno de los roles indicados. */
function require_role(string ...$roles): array
{
    $user = current_user();
    if ($user === null) {
        flash('warn', 'Ingresa con tu usuario para continuar.');
        sdb_redirect('login.php');
    }
    if ($roles !== [] && !in_array($user['role'], $roles, true)) {
        flash('error', 'No tienes permisos para acceder a esa seccion.');
        sdb_redirect(panel_url_for($user['role']));
    }

    return $user;
}

function panel_url_for(string $role): string
{
    return $role === 'admin' ? 'admin.php' : 'bomberos.php';
}

/* ------------------------------------------------------------- Ingreso ---- */

/** Intentos fallidos recientes para usuario + IP. */
function login_recent_failures(string $username, string $ip): int
{
    $since = gmdate('Y-m-d H:i:s', time() - LOGIN_LOCK_WINDOW);
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE success = 0 AND created_at > ? AND (username = ? OR ip = ?)'
    );
    $stmt->execute([$since, $username, $ip]);

    return (int) $stmt->fetchColumn();
}

function login_record_attempt(string $username, string $ip, bool $success): void
{
    $stmt = db()->prepare(
        'INSERT INTO login_attempts (username, ip, success, created_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([mb_substr($username, 0, 60), $ip, $success ? 1 : 0, now_utc()]);

    // Limpieza de registros antiguos (mas de 7 dias).
    db()->prepare('DELETE FROM login_attempts WHERE created_at < ?')
        ->execute([gmdate('Y-m-d H:i:s', time() - 604800)]);
}

/**
 * Valida credenciales para el rol solicitado.
 *
 * @return array{ok:bool, error?:string, user?:array}
 */
function attempt_login(string $username, string $password, string $expectedRole): array
{
    session_boot();
    $ip = client_ip();

    if ($username === '' || $password === '') {
        return ['ok' => false, 'error' => 'Completa usuario y contrasena.'];
    }

    if (login_recent_failures($username, $ip) >= LOGIN_MAX_ATTEMPTS) {
        audit('login_bloqueado', 'usuario: ' . $username, ['id' => null, 'username' => $username]);

        return [
            'ok'    => false,
            'error' => 'Demasiados intentos fallidos. Espera ' . intdiv(LOGIN_LOCK_WINDOW, 60) . ' minutos e intenta de nuevo.',
        ];
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Mensaje unico para no revelar si el usuario existe.
    $genericError = 'Usuario o contrasena incorrectos.';

    if ($user === false || !password_verify($password, $user['password_hash'])) {
        login_record_attempt($username, $ip, false);

        return ['ok' => false, 'error' => $genericError];
    }

    if ((int) $user['active'] !== 1) {
        login_record_attempt($username, $ip, false);

        return ['ok' => false, 'error' => 'Tu cuenta esta desactivada. Contacta al administrador del cuartel.'];
    }

    if ($user['role'] !== $expectedRole) {
        login_record_attempt($username, $ip, false);
        $donde = $user['role'] === 'admin' ? 'Administracion' : 'Bomberos';

        return ['ok' => false, 'error' => 'Esa cuenta pertenece al acceso de ' . $donde . '. Cambia de pestana e intenta alli.'];
    }

    // Rehash si cambio el algoritmo por defecto.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    login_record_attempt($username, $ip, true);
    session_regenerate_id(true);

    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['last_seen'] = time();

    db()->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')
        ->execute([now_utc(), $user['id']]);

    audit('ingreso', 'rol: ' . $user['role'], $user);

    return ['ok' => true, 'user' => $user];
}

function logout(): void
{
    session_boot();
    $user = current_user();
    if ($user !== null) {
        audit('salida', '', $user);
    }
    session_destroy_all();
}

/** Reglas minimas de contrasena. Devuelve null si es valida. */
function password_problem(string $password, string $confirm): ?string
{
    if (mb_strlen($password) < MIN_PASSWORD_LENGTH) {
        return 'La contrasena debe tener al menos ' . MIN_PASSWORD_LENGTH . ' caracteres.';
    }
    if ($password !== $confirm) {
        return 'Las contrasenas no coinciden.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'La contrasena debe combinar letras y numeros.';
    }

    return null;
}
