<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/* --------------------------------------------------------------- Alertas -- */

/** Orden por criticidad para paneles y portada. */
const SEVERITY_ORDER_SQL = "CASE severity WHEN 'critica' THEN 0 WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3 END";

/** Alertas visibles al publico (publicadas y no cerradas). */
function alerts_public(int $limit = 20): array
{
    $sql = 'SELECT a.*, u.full_name AS author_name, u.rank AS author_rank
            FROM alerts a JOIN users u ON u.id = a.created_by
            WHERE a.published = 1 AND a.status <> \'cerrada\'
            ORDER BY ' . SEVERITY_ORDER_SQL . ', a.created_at DESC
            LIMIT ?';
    $stmt = db()->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/** Ultimas alertas cerradas, para el historial publico. */
function alerts_recently_closed(int $limit = 5): array
{
    $stmt = db()->prepare(
        'SELECT a.*, u.full_name AS author_name
         FROM alerts a JOIN users u ON u.id = a.created_by
         WHERE a.published = 1 AND a.status = \'cerrada\'
         ORDER BY COALESCE(a.closed_at, a.updated_at) DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Listado para paneles.
 *
 * @param array{author?:int, status?:string, severity?:string, search?:string} $filters
 */
function alerts_list(array $filters = [], int $limit = 200): array
{
    $where  = [];
    $params = [];

    if (!empty($filters['author'])) {
        $where[]  = 'a.created_by = ?';
        $params[] = (int) $filters['author'];
    }
    if (!empty($filters['status']) && in_array($filters['status'], ALERT_STATUSES, true)) {
        $where[]  = 'a.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['severity']) && in_array($filters['severity'], ALERT_SEVERITIES, true)) {
        $where[]  = 'a.severity = ?';
        $params[] = $filters['severity'];
    }
    if (!empty($filters['search'])) {
        $where[]  = '(a.title LIKE ? OR a.location LIKE ? OR a.description LIKE ?)';
        $needle   = '%' . $filters['search'] . '%';
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
    }

    $sql = 'SELECT a.*, u.full_name AS author_name, u.username AS author_user
            FROM alerts a JOIN users u ON u.id = a.created_by';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY (a.status = \'cerrada\'), ' . SEVERITY_ORDER_SQL . ', a.created_at DESC LIMIT ' . $limit;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function alert_find(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT a.*, u.full_name AS author_name, u.username AS author_user
         FROM alerts a JOIN users u ON u.id = a.created_by WHERE a.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Valida los campos enviados desde el formulario de alertas.
 *
 * @return array{0:array<string,mixed>,1:string[]}
 */
function alert_validate(): array
{
    $errors = [];

    $data = [
        'title'         => post_str('title', 120),
        'type'          => post_str('type', 60),
        'severity'      => post_str('severity', 20),
        'status'        => post_str('status', 20),
        'location'      => post_str('location', 160),
        'meeting_point' => post_str('meeting_point', 120),
        'description'   => post_str('description', 2000),
        'instructions'  => post_str('instructions', 800),
        'published'     => isset($_POST['published']) ? 1 : 0,
    ];

    if (mb_strlen($data['title']) < 5) {
        $errors[] = 'El titulo debe tener al menos 5 caracteres.';
    }
    if (!in_array($data['type'], ALERT_TYPES, true)) {
        $errors[] = 'Selecciona un tipo de emergencia valido.';
    }
    if (!in_array($data['severity'], ALERT_SEVERITIES, true)) {
        $errors[] = 'Selecciona un nivel de severidad valido.';
    }
    if (!in_array($data['status'], ALERT_STATUSES, true)) {
        $errors[] = 'Selecciona un estado valido.';
    }
    if (mb_strlen($data['location']) < 3) {
        $errors[] = 'Indica la ubicacion del incidente.';
    }
    if (mb_strlen($data['description']) < 10) {
        $errors[] = 'La descripcion debe tener al menos 10 caracteres.';
    }

    return [$data, $errors];
}

function alert_create(array $data, int $authorId): int
{
    $now  = now_utc();
    $stmt = db()->prepare(
        'INSERT INTO alerts (title, type, severity, status, location, meeting_point, description,
                             instructions, published, created_by, created_at, updated_at, closed_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['title'], $data['type'], $data['severity'], $data['status'], $data['location'],
        $data['meeting_point'], $data['description'], $data['instructions'], $data['published'],
        $authorId, $now, $now, $data['status'] === 'cerrada' ? $now : null,
    ]);

    return (int) db()->lastInsertId();
}

function alert_update(int $id, array $data): void
{
    $current = alert_find($id);
    $closedAt = $current['closed_at'] ?? null;
    if ($data['status'] === 'cerrada' && $closedAt === null) {
        $closedAt = now_utc();
    } elseif ($data['status'] !== 'cerrada') {
        $closedAt = null;
    }

    $stmt = db()->prepare(
        'UPDATE alerts SET title = ?, type = ?, severity = ?, status = ?, location = ?, meeting_point = ?,
                           description = ?, instructions = ?, published = ?, updated_at = ?, closed_at = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $data['title'], $data['type'], $data['severity'], $data['status'], $data['location'],
        $data['meeting_point'], $data['description'], $data['instructions'], $data['published'],
        now_utc(), $closedAt, $id,
    ]);
}

function alert_set_status(int $id, string $status): void
{
    if (!in_array($status, ALERT_STATUSES, true)) {
        return;
    }
    $stmt = db()->prepare('UPDATE alerts SET status = ?, updated_at = ?, closed_at = ? WHERE id = ?');
    $stmt->execute([$status, now_utc(), $status === 'cerrada' ? now_utc() : null, $id]);
}

function alert_delete(int $id): void
{
    db()->prepare('DELETE FROM alerts WHERE id = ?')->execute([$id]);
}

/** Resumen para tarjetas de estado. */
function alerts_stats(): array
{
    $row = db()->query(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'activa' THEN 1 ELSE 0 END) AS activas,
            SUM(CASE WHEN status = 'controlada' THEN 1 ELSE 0 END) AS controladas,
            SUM(CASE WHEN status = 'cerrada' THEN 1 ELSE 0 END) AS cerradas,
            SUM(CASE WHEN severity = 'critica' AND status <> 'cerrada' THEN 1 ELSE 0 END) AS criticas
         FROM alerts"
    )->fetch();

    return array_map(static fn ($v) => (int) $v, $row ?: []);
}

/** Fecha de la ultima actualizacion registrada en el sistema. */
function alerts_last_update(): ?string
{
    $value = db()->query('SELECT MAX(updated_at) FROM alerts')->fetchColumn();

    return is_string($value) && $value !== '' ? $value : null;
}

/**
 * Resumen del estado del edificio, compartido por la portada y la API.
 *
 * @return array{titulo:string, detalle:string, en_alarma:bool, activas:int, criticas:int, total:int}
 */
function alerts_summary(?array $alerts = null): array
{
    $alerts ??= alerts_public();
    $activas  = count(array_filter($alerts, static fn (array $a) => $a['status'] === 'activa'));
    $criticas = count(array_filter($alerts, static fn (array $a) => $a['severity'] === 'critica'));
    $last     = alerts_last_update();

    $titulo = match (true) {
        $criticas > 0   => 'Emergencia critica en curso',
        $activas === 1  => '1 alerta activa',
        $activas > 1    => $activas . ' alertas activas',
        $alerts !== []  => 'Situacion controlada',
        default         => 'Sin alertas activas',
    };

    return [
        'titulo'    => $titulo,
        'detalle'   => $last !== null ? 'Ultima actualizacion: ' . fmt_ago($last) : 'Sistema en linea, sin reportes cargados',
        'en_alarma' => $activas > 0,
        'activas'   => $activas,
        'criticas'  => $criticas,
        'total'     => count($alerts),
    ];
}

/* -------------------------------------------------------------- Usuarios -- */

function users_list(?string $role = null): array
{
    if ($role !== null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE role = ? ORDER BY active DESC, full_name COLLATE NOCASE');
        $stmt->execute([$role]);

        return $stmt->fetchAll();
    }

    return db()->query('SELECT * FROM users ORDER BY role, active DESC, full_name COLLATE NOCASE')->fetchAll();
}

function user_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function username_taken(string $username, ?int $exceptId = null): bool
{
    $sql    = 'SELECT COUNT(*) FROM users WHERE username = ?';
    $params = [$username];
    if ($exceptId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $exceptId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() > 0;
}

/** El nombre de usuario admite letras, numeros, punto, guion y guion bajo. */
function username_problem(string $username): ?string
{
    if (!preg_match('/^[a-zA-Z0-9._-]{4,30}$/', $username)) {
        return 'El usuario debe tener entre 4 y 30 caracteres (letras, numeros, punto, guion o guion bajo).';
    }

    return null;
}

function user_create(array $data, string $password, ?int $createdBy, bool $mustChange = true): int
{
    $stmt = db()->prepare(
        'INSERT INTO users (username, password_hash, full_name, role, rank, phone, active,
                            must_change_password, created_at, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['username'],
        password_hash($password, PASSWORD_DEFAULT),
        $data['full_name'],
        $data['role'],
        $data['rank'],
        $data['phone'],
        $data['active'],
        $mustChange ? 1 : 0,
        now_utc(),
        $createdBy,
    ]);

    return (int) db()->lastInsertId();
}

function user_update(int $id, array $data): void
{
    $stmt = db()->prepare(
      'UPDATE users SET username = ?, full_name = ?, `rank` = ?, phone = ?, active = ? WHERE id = ?'
    );
    $stmt->execute([$data['username'], $data['full_name'], $data['rank'], $data['phone'], $data['active'], $id]);
}

function user_set_password(int $id, string $password, bool $mustChange = false): void
{
    $stmt = db()->prepare('UPDATE users SET password_hash = ?, must_change_password = ? WHERE id = ?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $mustChange ? 1 : 0, $id]);
}

function user_set_active(int $id, bool $active): void
{
    db()->prepare('UPDATE users SET active = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
}

function user_delete(int $id): void
{
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
}

/** Cantidad de alertas cargadas por un usuario (impide borrarlo sin querer). */
function user_alert_count(int $id): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM alerts WHERE created_by = ?');
    $stmt->execute([$id]);

    return (int) $stmt->fetchColumn();
}

function users_stats(): array
{
    $row = db()->query(
        "SELECT
            SUM(CASE WHEN role = 'bombero' THEN 1 ELSE 0 END) AS bomberos,
            SUM(CASE WHEN role = 'bombero' AND active = 1 THEN 1 ELSE 0 END) AS bomberos_activos,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admins
         FROM users"
    )->fetch();

    return array_map(static fn ($v) => (int) $v, $row ?: []);
}

/* ------------------------------------------------------------- Contactos -- */

function contacts_all(): array
{
    return db()->query('SELECT * FROM contacts ORDER BY position, id')->fetchAll();
}

function contact_create(string $label, string $number, string $tone, int $position): void
{
    db()->prepare('INSERT INTO contacts (label, number, tone, position) VALUES (?, ?, ?, ?)')
        ->execute([$label, $number, $tone, $position]);
}

function contact_update(int $id, string $label, string $number, string $tone, int $position): void
{
    db()->prepare('UPDATE contacts SET label = ?, number = ?, tone = ?, position = ? WHERE id = ?')
        ->execute([$label, $number, $tone, $position, $id]);
}

function contact_delete(int $id): void
{
    db()->prepare('DELETE FROM contacts WHERE id = ?')->execute([$id]);
}

/* ----------------------------------------------------------- Suscriptores -- */

function subscriber_phone_taken(string $phone): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM subscribers WHERE phone = ?');
    $stmt->execute([$phone]);

    return (int) $stmt->fetchColumn() > 0;
}

function subscriber_create(string $name, string $phone): int
{
    $stmt = db()->prepare('INSERT INTO subscribers (name, phone, active, created_at) VALUES (?, ?, 1, ?)');
    $stmt->execute([$name, $phone, now_utc()]);

    return (int) db()->lastInsertId();
}

function subscribers_all(): array
{
    return db()->query('SELECT * FROM subscribers ORDER BY active DESC, created_at DESC')->fetchAll();
}

function subscriber_set_active(int $id, bool $active): void
{
    db()->prepare('UPDATE subscribers SET active = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
}

function subscriber_delete(int $id): void
{
    db()->prepare('DELETE FROM subscribers WHERE id = ?')->execute([$id]);
}

function subscribers_count_active(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM subscribers WHERE active = 1')->fetchColumn();
}

/* -------------------------------------------------------------- Bitacora -- */

function audit_recent(int $limit = 25): array
{
    $stmt = db()->prepare('SELECT * FROM audit_log ORDER BY id DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}
