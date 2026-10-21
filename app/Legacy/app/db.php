<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

/** Conexion unica (PDO). Crea el esquema la primera vez. Motor segun DB_DRIVER. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = DB_DRIVER === 'mysql' ? db_connect_mysql() : db_connect_sqlite();

    if (DB_DRIVER === 'mysql') {
        db_install_mysql($pdo);
    } else {
        db_install_sqlite($pdo);
    }

    return $pdo;
}

function db_connect_sqlite(): PDO
{
    if (!is_dir(DATA_DIR) && !mkdir(DATA_DIR, 0775, true) && !is_dir(DATA_DIR)) {
        throw new RuntimeException('No se pudo crear el directorio de datos: ' . DATA_DIR);
    }

    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    return $pdo;
}

function db_connect_mysql(): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_MYSQL_HOST,
        DB_MYSQL_PORT,
        DB_MYSQL_NAME
    );

    return new PDO($dsn, DB_MYSQL_USER, DB_MYSQL_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/** Crea tablas e inserta los datos iniciales en SQLite (idempotente). */
function db_install_sqlite(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id                   INTEGER PRIMARY KEY AUTOINCREMENT,
            username             TEXT NOT NULL COLLATE NOCASE UNIQUE,
            password_hash        TEXT NOT NULL,
            full_name            TEXT NOT NULL,
            role                 TEXT NOT NULL CHECK (role IN ('admin','bombero')),
            "rank"                 TEXT NOT NULL DEFAULT '',
            phone                TEXT NOT NULL DEFAULT '',
            active               INTEGER NOT NULL DEFAULT 1,
            must_change_password INTEGER NOT NULL DEFAULT 0,
            created_at           TEXT NOT NULL,
            created_by           INTEGER REFERENCES users(id) ON DELETE SET NULL,
            last_login_at        TEXT
        )
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS alerts (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            title         TEXT NOT NULL,
            type          TEXT NOT NULL,
            severity      TEXT NOT NULL CHECK (severity IN ('baja','media','alta','critica')),
            status        TEXT NOT NULL CHECK (status IN ('activa','controlada','cerrada')),
            location      TEXT NOT NULL,
            meeting_point TEXT NOT NULL DEFAULT '',
            description   TEXT NOT NULL,
            instructions  TEXT NOT NULL DEFAULT '',
            published     INTEGER NOT NULL DEFAULT 1,
            created_by    INTEGER NOT NULL REFERENCES users(id),
            created_at    TEXT NOT NULL,
            updated_at    TEXT NOT NULL,
            closed_at     TEXT
        )
    SQL);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_alerts_status ON alerts(status, published)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_alerts_author ON alerts(created_by)');

    // Migracion incremental: agrega la columna "zone" si la base ya existia
    // sin ella (bases creadas antes de que se sumara el filtro por zona).
    $alertColumns = $pdo->query('PRAGMA table_info(alerts)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('zone', $alertColumns, true)) {
        $pdo->exec("ALTER TABLE alerts ADD COLUMN zone TEXT NOT NULL DEFAULT ''");
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_alerts_zone ON alerts(zone)');

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS contacts (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            label    TEXT NOT NULL,
            number   TEXT NOT NULL,
            tone     TEXT NOT NULL DEFAULT 'red',
            position INTEGER NOT NULL DEFAULT 0
        )
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS subscribers (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT NOT NULL DEFAULT '',
            phone      TEXT NOT NULL UNIQUE,
            active     INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL
        )
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS audit_log (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER,
            username   TEXT NOT NULL DEFAULT '',
            action     TEXT NOT NULL,
            detail     TEXT NOT NULL DEFAULT '',
            ip         TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL
        )
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS login_attempts (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            username   TEXT NOT NULL,
            ip         TEXT NOT NULL,
            success    INTEGER NOT NULL,
            created_at TEXT NOT NULL
        )
    SQL);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attempts ON login_attempts(username, ip, created_at)');

    // Tokens de dispositivo para notificaciones push (Firebase Cloud Messaging).
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS device_tokens (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            token         TEXT NOT NULL UNIQUE,
            zone          TEXT NOT NULL DEFAULT '',
            active        INTEGER NOT NULL DEFAULT 1,
            created_at    TEXT NOT NULL,
            last_seen_at  TEXT NOT NULL
        )
    SQL);
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_device_tokens_active ON device_tokens(active)');

    db_seed($pdo);
}

/**
 * Crea tablas e inserta los datos iniciales en MySQL/MariaDB (idempotente).
 * Mismo esquema logico que db_install_sqlite(), traducido a sintaxis MySQL
 * (AUTO_INCREMENT, ENGINE=InnoDB, utf8mb4, DATETIME en vez de TEXT, etc.).
 */
function db_install_mysql(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username             VARCHAR(60) NOT NULL UNIQUE,
            password_hash        VARCHAR(255) NOT NULL,
            full_name            VARCHAR(120) NOT NULL,
            role                 ENUM('admin','bombero') NOT NULL,
            `rank`               VARCHAR(60) NOT NULL DEFAULT '',
            phone                VARCHAR(30) NOT NULL DEFAULT '',
            active               TINYINT(1) NOT NULL DEFAULT 1,
            must_change_password TINYINT(1) NOT NULL DEFAULT 0,
            created_at           DATETIME NOT NULL,
            created_by           INT UNSIGNED NULL,
            last_login_at        DATETIME NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS alerts (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title         VARCHAR(120) NOT NULL,
            type          VARCHAR(60) NOT NULL,
            severity      ENUM('baja','media','alta','critica') NOT NULL,
            status        ENUM('activa','controlada','cerrada') NOT NULL,
            location      VARCHAR(160) NOT NULL,
            zone          VARCHAR(60) NOT NULL DEFAULT '',
            meeting_point VARCHAR(120) NOT NULL DEFAULT '',
            description   TEXT NOT NULL,
            instructions  TEXT NOT NULL,
            published     TINYINT(1) NOT NULL DEFAULT 1,
            created_by    INT UNSIGNED NOT NULL,
            created_at    DATETIME NOT NULL,
            updated_at    DATETIME NOT NULL,
            closed_at     DATETIME NULL,
            FOREIGN KEY (created_by) REFERENCES users(id),
            INDEX idx_alerts_status (status, published),
            INDEX idx_alerts_author (created_by),
            INDEX idx_alerts_zone (zone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS contacts (
            id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            label    VARCHAR(60) NOT NULL,
            number   VARCHAR(60) NOT NULL,
            tone     VARCHAR(20) NOT NULL DEFAULT 'red',
            position INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS subscribers (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(80) NOT NULL DEFAULT '',
            phone      VARCHAR(30) NOT NULL UNIQUE,
            active     TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS audit_log (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT UNSIGNED NULL,
            username   VARCHAR(60) NOT NULL DEFAULT '',
            action     VARCHAR(60) NOT NULL,
            detail     VARCHAR(500) NOT NULL DEFAULT '',
            ip         VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS login_attempts (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username   VARCHAR(60) NOT NULL,
            ip         VARCHAR(45) NOT NULL,
            success    TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_attempts (username, ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS device_tokens (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token         VARCHAR(255) NOT NULL UNIQUE,
            zone          VARCHAR(60) NOT NULL DEFAULT '',
            active        TINYINT(1) NOT NULL DEFAULT 1,
            created_at    DATETIME NOT NULL,
            last_seen_at  DATETIME NOT NULL,
            INDEX idx_device_tokens_active (active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    db_seed($pdo);
}

/** Datos iniciales, iguales para cualquier motor. */
function db_seed(PDO $pdo): void
{
    // Administrador inicial.
    $hasAdmin = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($hasAdmin === 0) {
        $stmt = $pdo->prepare(
                   'INSERT INTO users (username, password_hash, full_name, role, `rank`, active, must_change_password, created_at)
             VALUES (?, ?, ?, \'admin\', ?, 1, 1, ?)'
        );
        $stmt->execute([
            BOOTSTRAP_ADMIN_USER,
            password_hash(BOOTSTRAP_ADMIN_PASS, PASSWORD_DEFAULT),
            BOOTSTRAP_ADMIN_NAME,
            'Jefatura',
            now_utc(),
        ]);
    }

    // Contactos visibles en la portada.
    $hasContacts = (int) $pdo->query('SELECT COUNT(*) FROM contacts')->fetchColumn();
    if ($hasContacts === 0) {
        $stmt = $pdo->prepare('INSERT INTO contacts (label, number, tone, position) VALUES (?, ?, ?, ?)');
        foreach ([
            ['Bomberos', '112', 'red', 1],
            ['Emergencias', '911', 'amber', 2],
            ['Punto de encuentro', 'Patio central', 'blue', 3],
        ] as $row) {
            $stmt->execute($row);
        }
    }
}

/** Registra una accion en la bitacora. */
function audit(string $action, string $detail = '', ?array $user = null): void
{
    $user ??= function_exists('current_user') ? current_user() : null;
    $stmt = db()->prepare(
        'INSERT INTO audit_log (user_id, username, action, detail, ip, created_at) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id'] ?? null,
        (string) ($user['username'] ?? 'anonimo'),
        $action,
        mb_substr($detail, 0, 500),
        client_ip(),
        now_utc(),
    ]);
}
