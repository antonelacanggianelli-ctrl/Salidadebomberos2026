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
 * Motor de base de datos (Semana 2 del plan de trabajo).
 *
 * Por defecto la app sigue usando SQLite (DB_DRIVER=sqlite): no requiere
 * un servidor aparte, así cualquiera del equipo la clona y la corre sin
 * instalar nada mas, ideal para desarrollo y para la demo de defensa.
 *
 * Para usar MySQL/MariaDB (como pedia originalmente el plan de trabajo),
 * definir la variable de entorno DB_DRIVER=mysql y completar el resto de
 * las variables DB_* antes de levantar el servidor. Ambos motores usan
 * exactamente el mismo esquema (ver db_install_sqlite/db_install_mysql
 * en db.php) y el resto de la aplicacion no cambia una linea.
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

/**
 * Zonas del partido para poder filtrar alertas por area geografica.
 * Ajustar esta lista con las zonas reales que use el cuartel.
 */
const ALERT_ZONES = [
    'Centro',
    'Zona Norte',
    'Zona Sur',
    'Zona Este / Laguna',
    'Zona Oeste',
    'Zona Rural',
];

// Configuracion de Firebase Cloud Messaging (notificaciones push del portal).
// Completar con las credenciales reales del proyecto de Firebase antes de
// habilitar el envio en produccion. Mientras esten vacias, el sistema sigue
// funcionando con normalidad: solo no se dispara la notificacion push.
const FCM_PROJECT_ID          = 'salidadebomberos';
// Gemini ya habia guardado la cuenta de servicio en app/Config/firebase-credentials.json
// (y ya esta en .gitignore ahi), asi que apuntamos a esa ruta en vez de duplicar el archivo.
const FCM_SERVICE_ACCOUNT_KEY = APPPATH . 'Config/firebase-credentials.json';
const FCM_VAPID_PUBLIC_KEY    = 'BJJswKANhRBmOxRw1vjZ2RheuGQG8TsvgSDfV1pQyo7KpaqH4kMta8qrQvsaRj2J7wZTwtadB1fedHuVUOucVQc';
const FCM_WEB_API_KEY         = 'AIzaSyCMnWUGnpEsVOOuMVWbKiWmtLKLRin1EdA';
const FCM_APP_ID              = '1:183433473749:web:50a22e0076c90c0d62bc5c';
const FCM_MESSAGING_SENDER_ID = '183433473749';

date_default_timezone_set('UTC');
