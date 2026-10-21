# Modelo conceptual y de datos

## Entidades principales

| Entidad | Propósito |
|---|---|
| `users` | Personas autorizadas, rol, estado y credenciales. |
| `alerts` | Incidentes publicados y su ciclo de vida. |
| `contacts` | Teléfonos y ubicaciones mostrados en la portada. |
| `subscribers` | Sin uso: quedó de la suscripción por SMS, que se eliminó. Se conserva para no romper bases ya instaladas. |
| `audit_log` | Trazabilidad de acciones del sistema. |
| `login_attempts` | Control de intentos fallidos y bloqueo temporal. |

## Relaciones

- Un usuario puede crear muchas alertas: `users 1 ─── N alerts`.
- Un usuario administrador puede crear muchas cuentas: `users 1 ─── N users` mediante `created_by`.
- Un usuario puede generar muchas entradas de bitácora: `users 1 ─── N audit_log`; la referencia puede quedar nula si la cuenta se elimina.
- `contacts`, `subscribers` y `login_attempts` son catálogos/soportes independientes.

## Atributos relevantes de `alerts`

`title`, `type`, `severity`, `status`, `location`, `meeting_point`, `description`, `instructions`, `published`, `created_by`, `created_at`, `updated_at` y `closed_at`.

## Integridad y restricciones

La base usa claves primarias autoincrementales, claves foráneas, `ON DELETE SET NULL` para preservar auditoría de usuarios eliminados, restricciones `CHECK` para rol, severidad y estado, campos obligatorios e índices para estado y autor de la alerta.

## Flujo conceptual

```text
Usuario autenticado
        |
        v
Formulario con CSRF y validaciones
        |
        v
Controlador de la página PHP
        |
        v
Repository + PDO
        |
        v
SQLite: users / alerts / contacts / audit_log
        |
        v
Vista HTML o respuesta JSON
```

## Fuente de verdad

El esquema ejecutable está definido en `app/db.php`, que crea las tablas de forma idempotente cuando se abre la aplicación por primera vez. La base incluida en `data/` sirve como estado inicial de demostración y debe respaldarse junto con sus archivos SQLite auxiliares cuando existan.
