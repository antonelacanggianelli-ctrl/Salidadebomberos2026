# Evidencia de pruebas ejecutadas

## Entorno de revisión

- PHP 8.3.6 CLI.
- Extensiones verificadas: `json`, `PDO`, `pdo_sqlite`, `session`, `sqlite3` y `mbstring`.
- Servidor: servidor embebido de PHP con `dev-server.php`.
- Base: copia escribible de la SQLite inicial para no modificar la base original entregada.
- Fecha de ejecución: completar con la fecha de la prueba del equipo antes de la defensa.

## Resultados ejecutados

| Caso | Resultado observado |
|---|---|
| Portada pública | **OK** — HTTP 200 y contenido de `SalidadeBomberos!`. |
| API de alertas | **OK** — HTTP 200 y JSON válido con resumen y lista de alertas. |
| API con recurso desconocido | **OK** — HTTP 404 con JSON de error. |
| Página de login de administrador | **OK** — HTTP 200 y formulario visible. |
| Login inicial del administrador | **OK** — HTTP 302 hacia `perfil.php`, solicitando cambio de contraseña. |
| Perfil autenticado | **OK** — HTTP 200 y contenido de `Mi perfil`. |
| Panel administrativo autenticado | **OK** — HTTP 200 y contenido de administración. |
| Protección de `app/` | **OK** — HTTP 403. |
| Protección de `data/` | **OK** — HTTP 403. |
| Sintaxis PHP | **OK** — sin errores de sintaxis en los 15 archivos PHP. |

## Pruebas todavía necesarias por el equipo

Deben ejecutarse y documentarse con datos reales del equipo los casos de creación de bombero, alta y edición de alerta, cambio de estados, edición de alerta ajena, CSRF inválido, bloqueo por intentos, último administrador, eliminación/suspensión de usuario, escape de HTML, backup/restauración y método POST contra la API.

Esta evidencia no reemplaza la matriz completa de `07-plan-pruebas.md`; sólo registra las verificaciones realizadas durante esta revisión.
