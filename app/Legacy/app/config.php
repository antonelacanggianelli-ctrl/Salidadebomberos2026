<?php
declare(strict_types=1);

/**
 * Configuracion general de SalidadeBomberos!
 * Ajusta aqui los valores del cuartel antes de publicar el sitio.
 */

const APP_NAME     = 'SalidadeBomberos!';
const APP_TAGLINE  = 'Central de alertas y evacuacion';
const APP_STATION  = 'Cuartel Central';

// Zona horaria usada para MOSTRAR fechas. Los datos se guardan siempre en UTC.
const APP_TIMEZONE = 'America/Argentina/Buenos_Aires';

// Almacenamiento (SQLite: no requiere configurar un servidor de base de datos).
const DATA_DIR = __DIR__ . '/../data';
const DB_FILE  = DATA_DIR . '/salidadebomberos.sqlite';

/**
 * Motor de base de datos.
 * Por defecto SQLite (DB_DRIVER=sqlite). Para MySQL/MariaDB definir la
 * variable de entorno DB_DRIVER=mysql y completar las variables DB_*.
 * Ambos motores usan el mismo esquema (ver db.php).
 */
define('DB_DRIVER', 'mysql' === (getenv('DB_DRIVER') ?: 'sqlite') ? 'mysql' : 'sqlite');
define('DB_MYSQL_HOST', false !== getenv('DB_HOST') ? getenv('DB_HOST') : '127.0.0.1');
define('DB_MYSQL_PORT', false !== getenv('DB_PORT') ? getenv('DB_PORT') : '3306');
define('DB_MYSQL_NAME', false !== getenv('DB_NAME') ? getenv('DB_NAME') : 'salidadebomberos');
define('DB_MYSQL_USER', false !== getenv('DB_USER') ? getenv('DB_USER') : 'salidadebomberos');
define('DB_MYSQL_PASS', false !== getenv('DB_PASS') ? getenv('DB_PASS') : '');

// Sesiones y seguridad.
const SESSION_NAME         = 'sdb_session';
const SESSION_IDLE_TIMEOUT = 1800;  // 30 minutos de inactividad
const LOGIN_MAX_ATTEMPTS   = 5;     // intentos fallidos antes del bloqueo temporal
const LOGIN_LOCK_WINDOW    = 900;   // ventana de bloqueo en segundos (15 minutos)
const MIN_PASSWORD_LENGTH  = 8;

/**
 * Administrador inicial. Se crea solo la primera vez que se abre el sitio.
 * Cambia la contrasena desde "Mi perfil" apenas ingreses.
 */
const BOOTSTRAP_ADMIN_USER = 'admin';
const BOOTSTRAP_ADMIN_PASS = 'Bomberos2026!';
const BOOTSTRAP_ADMIN_NAME = 'Administrador del sistema';

// Catalogos usados por los formularios de alertas.
const ALERT_TYPES = [
    'Incendio estructural',
    'Incendio forestal',
    'Rescate de personas',
    'Accidente vehicular',
    'Materiales peligrosos',
    'Fuga de gas',
    'Inundacion',
    'Simulacro',
    'Otro',
];

const ALERT_SEVERITIES = ['baja', 'media', 'alta', 'critica'];
const ALERT_STATUSES   = ['activa', 'controlada', 'cerrada'];

// Zonas del partido para poder filtrar alertas por area geografica.
const ALERT_ZONES = [
    'Centro',
    'Zona Norte',
    'Zona Sur',
    'Zona Este / Laguna',
    'Zona Oeste',
    'Zona Rural',
];

// Configuracion de Firebase Cloud Messaging (notificaciones push del portal).
// En Render, la cuenta de servicio se carga como Secret File
// (/etc/secrets/firebase-credentials.json) y las claves web como variables
// de entorno. En local se usa el archivo de la carpeta data/.
$fcmKeyFile = DATA_DIR . '/firebase-service-account.json';
foreach ([
    '/var/www/html/app/Config/firebase-credentials.json',
    '/etc/secrets/firebase-credentials.json',
    DATA_DIR . '/firebase-service-account.json',
] as $fcmCandidate) {
    if (is_readable($fcmCandidate)) {
        $fcmKeyFile = $fcmCandidate;
        break;
    }
}

// El project_id tambien esta dentro del JSON de credenciales.
$fcmProjectFromKey = '';
if (is_readable($fcmKeyFile)) {
    $decoded = json_decode((string) @file_get_contents($fcmKeyFile), true);
    $fcmProjectFromKey = is_array($decoded) ? (string) ($decoded['project_id'] ?? '') : '';
}

define('FCM_PROJECT_ID', getenv('FCM_PROJECT_ID') ?: $fcmProjectFromKey);
define('FCM_SERVICE_ACCOUNT_KEY', getenv('FCM_SERVICE_ACCOUNT_KEY') ?: $fcmKeyFile);
define('FCM_VAPID_PUBLIC_KEY', getenv('FCM_VAPID_PUBLIC_KEY') ?: '');
define('FCM_WEB_API_KEY', getenv('FCM_WEB_API_KEY') ?: '');
define('FCM_APP_ID', getenv('FCM_APP_ID') ?: '');
define('FCM_MESSAGING_SENDER_ID', getenv('FCM_MESSAGING_SENDER_ID') ?: '');

date_default_timezone_set('UTC');