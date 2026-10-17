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

date_default_timezone_set('UTC');
