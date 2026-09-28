<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo CI4 de alertas.
 *
 * Primer paso concreto de la migracion mencionada en el README: la logica de
 * acceso a datos de alertas empieza a vivir en un Model real de CodeIgniter,
 * en lugar de funciones PDO sueltas dentro de app/Legacy/app/repository.php.
 *
 * app/Legacy/app/repository.php delega en este Model para no romper a
 * bomberos.php, admin.php, index.php ni api.php, que siguen llamando a las
 * mismas funciones de siempre (alerts_public(), alerts_list(), etc.).
 */
class AlertModel extends Model
{
    protected $table            = 'alerts';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'title', 'type', 'severity', 'status', 'location', 'zone',
        'meeting_point', 'description', 'instructions', 'published',
        'created_by', 'created_at', 'updated_at', 'closed_at',
    ];

    /** Orden por criticidad, reutilizado en portada y paneles. */
    public const SEVERITY_ORDER_SQL = "CASE severity WHEN 'critica' THEN 0 WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3 END";

    private function baseSelect()
    {
        return $this->select('alerts.*, users.full_name AS author_name, users.rank AS author_rank, users.username AS author_user')
            ->join('users', 'users.id = alerts.created_by');
    }

    /** Alertas visibles al publico: publicadas y no cerradas. */
    public function publicAlerts(int $limit = 20): array
    {
        return $this->baseSelect()
            ->where('alerts.published', 1)
            ->where('alerts.status !=', 'cerrada')
            ->orderBy(self::SEVERITY_ORDER_SQL, '', false)
            ->orderBy('alerts.created_at', 'DESC')
            ->limit($limit)
            ->find();
    }

    /** Ultimas alertas cerradas, para el historial publico. */
    public function recentlyClosed(int $limit = 5): array
    {
        return $this->baseSelect()
            ->where('alerts.published', 1)
            ->where('alerts.status', 'cerrada')
            ->orderBy('COALESCE(alerts.closed_at, alerts.updated_at)', 'DESC', false)
            ->limit($limit)
            ->find();
    }

    /**
     * Listado para paneles y portal, con filtros opcionales.
     *
     * @param array{author?:int, status?:string, severity?:string, type?:string,
     *              zone?:string, search?:string, published_only?:bool} $filters
     */
    public function listWithFilters(array $filters = [], int $limit = 200): array
    {
        $builder = $this->baseSelect();

        if (!empty($filters['author'])) {
            $builder->where('alerts.created_by', (int) $filters['author']);
        }
        if (!empty($filters['status']) && in_array($filters['status'], ALERT_STATUSES, true)) {
            $builder->where('alerts.status', $filters['status']);
        }
        if (!empty($filters['severity']) && in_array($filters['severity'], ALERT_SEVERITIES, true)) {
            $builder->where('alerts.severity', $filters['severity']);
        }
        if (!empty($filters['type']) && in_array($filters['type'], ALERT_TYPES, true)) {
            $builder->where('alerts.type', $filters['type']);
        }
        if (!empty($filters['zone']) && in_array($filters['zone'], ALERT_ZONES, true)) {
            $builder->where('alerts.zone', $filters['zone']);
        }
        if (!empty($filters['published_only'])) {
            $builder->where('alerts.published', 1);
        }
        if (!empty($filters['search'])) {
            $builder->groupStart()
                ->like('alerts.title', $filters['search'])
                ->orLike('alerts.location', $filters['search'])
                ->orLike('alerts.description', $filters['search'])
                ->groupEnd();
        }

        return $builder
            ->orderBy("(alerts.status = 'cerrada')", '', false)
            ->orderBy(self::SEVERITY_ORDER_SQL, '', false)
            ->orderBy('alerts.created_at', 'DESC')
            ->limit($limit)
            ->find();
    }

    public function findWithAuthor(int $id): ?array
    {
        $row = $this->baseSelect()->where('alerts.id', $id)->first();

        return $row ?: null;
    }

    public function stats(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'activa' THEN 1 ELSE 0 END) AS activas,
                SUM(CASE WHEN status = 'controlada' THEN 1 ELSE 0 END) AS controladas,
                SUM(CASE WHEN status = 'cerrada' THEN 1 ELSE 0 END) AS cerradas,
                SUM(CASE WHEN severity = 'critica' AND status <> 'cerrada' THEN 1 ELSE 0 END) AS criticas
             FROM alerts"
        )->getRowArray();

        return array_map(static fn ($v) => (int) $v, $row ?: []);
    }

    public function lastUpdate(): ?string
    {
        $value = $this->db->query('SELECT MAX(updated_at) AS m FROM alerts')->getRowArray();

        return ($value['m'] ?? null) !== null && $value['m'] !== '' ? $value['m'] : null;
    }
}
