<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

/** Conexion unica (PDO + SQLite). Crea el esquema la primera vez. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

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

    db_install($pdo);

    return $pdo;
}

/** Crea tablas e inserta los datos iniciales (idempotente). */
function db_install(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id                   INTEGER PRIMARY KEY AUTOINCREMENT,
            username             TEXT NOT NULL COLLATE NOCASE UNIQUE,
            password_hash        TEXT NOT NULL,
            full_name            TEXT NOT NULL,
            role                 TEXT NOT NULL CHECK (role IN ('admin','bombero')),
            rank                 TEXT NOT NULL DEFAULT '',
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

    // Administrador inicial.
    $hasAdmin = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($hasAdmin === 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, full_name, role, rank, active, must_change_password, created_at)
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
