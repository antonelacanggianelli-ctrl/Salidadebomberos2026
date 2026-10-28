-- Tabla de sesiones (MySQL / MariaDB). Ejecutar UNA vez en cada base
-- (primero en la base de PRUEBA). Necesaria cuando SESSION_DRIVER=database.
CREATE TABLE IF NOT EXISTS app_sessions (
    id            VARCHAR(128) NOT NULL,
    data          MEDIUMTEXT   NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_app_sessions_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
