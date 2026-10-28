<?php
declare(strict_types=1);

/**
 * Sesiones guardadas en la base de datos (tabla app_sessions).
 * Se usa cuando SESSION_DRIVER=database (Vercel no tiene disco persistente,
 * y cada solicitud puede ser atendida por una instancia distinta).
 * La tabla se crea con docs/sql/sessions.sql, no desde una solicitud pública.
 */
final class DbSessionHandler implements SessionHandlerInterface
{
    public function __construct(private PDO $pdo, private int $ttl)
    {
    }

    public function open(string $path, string $name): bool
    {
        // Solo para entornos de PRUEBA: crea la tabla si falta (SESSION_AUTO_CREATE=1).
        // En producción la tabla se crea con docs/sql/sessions.sql.
        if (getenv('SESSION_AUTO_CREATE') === '1') {
            $mysql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
            $this->pdo->exec(
                $mysql
                    ? 'CREATE TABLE IF NOT EXISTS app_sessions (
                        id VARCHAR(128) NOT NULL, data MEDIUMTEXT NOT NULL,
                        last_activity INT UNSIGNED NOT NULL, PRIMARY KEY (id),
                        KEY idx_app_sessions_last_activity (last_activity)
                      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
                    : 'CREATE TABLE IF NOT EXISTS app_sessions (
                        id VARCHAR(128) PRIMARY KEY, data TEXT NOT NULL, last_activity INTEGER NOT NULL)'
            );
        }

        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $st = $this->pdo->prepare('SELECT data FROM app_sessions WHERE id = ? AND last_activity > ?');
        $st->execute([$id, time() - $this->ttl]);
        $data = $st->fetchColumn();

        return $data === false ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        $mysql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $sql   = $mysql
            ? 'INSERT INTO app_sessions (id, data, last_activity) VALUES (?, ?, ?)
               ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)'
            : 'INSERT INTO app_sessions (id, data, last_activity) VALUES (?, ?, ?)
               ON CONFLICT(id) DO UPDATE SET data = excluded.data, last_activity = excluded.last_activity';

        return $this->pdo->prepare($sql)->execute([$id, $data, time()]);
    }

    public function destroy(string $id): bool
    {
        return $this->pdo->prepare('DELETE FROM app_sessions WHERE id = ?')->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $st = $this->pdo->prepare('DELETE FROM app_sessions WHERE last_activity < ?');
        $st->execute([time() - $this->ttl]);

        return $st->rowCount();
    }
}
